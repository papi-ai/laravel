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

class LaravelQueue implements QueueInterface
{
    public function __construct(
        private readonly string $queue = 'default',
        private readonly string $table = 'papi_jobs',
    ) {
    }

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
