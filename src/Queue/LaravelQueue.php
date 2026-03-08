<?php

/*
 * This file is part of PapiAI,
 * A simple but powerful PHP library for building AI agents.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PapiAI\Laravel\Queue;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PapiAI\Core\AgentJob;
use PapiAI\Core\Contracts\QueueInterface;
use PapiAI\Core\JobStatus;

/**
 * Laravel queue adapter for dispatching asynchronous agent jobs.
 *
 * Pushes agent work onto a Laravel queue and tracks job progress in a
 * database table, allowing callers to poll for completion status and
 * retrieve results or errors after execution.
 */
class LaravelQueue implements QueueInterface
{
    /**
     * @param string $queue Laravel queue name to dispatch jobs to
     * @param string $table Database table name for tracking job status
     */
    public function __construct(
        private readonly string $queue = 'default',
        private readonly string $table = 'papi_jobs',
    ) {
    }

    /**
     * Dispatch an agent job onto the Laravel queue for async processing.
     *
     * Creates a tracking record in the database, then pushes the job onto
     * the configured queue. The queued closure resolves the agent from the
     * container, runs the prompt, and updates the tracking record with the
     * result or error.
     *
     * @param AgentJob $job The agent job containing prompt, agent class, and options
     *
     * @return string UUID assigned to the dispatched job for status polling
     *
     * @throws \JsonException When the job payload cannot be JSON-encoded
     */
    public function dispatch(AgentJob $job): string
    {
        $jobId = Str::uuid()->toString();

        DB::table($this->table)->insert([
            'id' => $jobId,
            'status' => JobStatus::PENDING,
            'payload' => json_encode($job->toArray(), JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Queue::push(function () use ($jobId, $job): void {
            DB::table($this->table)
                ->where('id', $jobId)
                ->update(['status' => JobStatus::RUNNING, 'updated_at' => now()]);

            try {
                /** @var class-string $agentClass */
                $agentClass = $job->agentClass;
                $agent = app($agentClass);
                $response = $agent->run($job->prompt, $job->options);

                DB::table($this->table)
                    ->where('id', $jobId)
                    ->update([
                        'status' => JobStatus::COMPLETED,
                        'result' => json_encode($response->text, JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $e) {
                DB::table($this->table)
                    ->where('id', $jobId)
                    ->update([
                        'status' => JobStatus::FAILED,
                        'error' => $e->getMessage(),
                        'updated_at' => now(),
                    ]);
            }
        }, '', $this->queue);

        return $jobId;
    }

    /**
     * Retrieve the current status of a dispatched job.
     *
     * Returns a PENDING status if no tracking record exists for the given ID,
     * which can occur if the job has not yet been persisted or the ID is invalid.
     *
     * @param string $jobId UUID of the job to check
     *
     * @return JobStatus Current status including any error message on failure
     */
    public function status(string $jobId): JobStatus
    {
        $record = DB::table($this->table)->where('id', $jobId)->first();

        if ($record === null) {
            return new JobStatus(
                jobId: $jobId,
                status: JobStatus::PENDING,
            );
        }

        return new JobStatus(
            jobId: $jobId,
            status: $record->status,
            error: $record->error ?? null,
        );
    }
}
