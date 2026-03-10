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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PapiAI\Core\AgentJob;
use PapiAI\Core\JobStatus;
use PapiAI\Laravel\Queue\LaravelQueue;

describe('LaravelQueue', function (): void {
    it('dispatches a job and returns a UUID', function (): void {
        $queue = new LaravelQueue();
        $job = new AgentJob(
            agentClass: 'PapiAI\Core\Agent',
            prompt: 'Hello',
            options: [],
        );

        DB::shouldReceive('table')
            ->with('papi_jobs')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('insert')
            ->once()
            ->withArgs(function (array $data) {
                return isset($data['id'])
                    && $data['status'] === JobStatus::PENDING
                    && isset($data['payload']);
            })
            ->andReturnTrue();

        Queue::shouldReceive('push')
            ->once()
            ->withArgs(function ($closure, $data, $queueName) {
                return is_callable($closure) && $queueName === 'default';
            });

        $jobId = $queue->dispatch($job);

        expect($jobId)->toBeString();
        expect(strlen($jobId))->toBe(36);
    });

    it('returns pending status for a tracked job', function (): void {
        $queue = new LaravelQueue();

        $record = (object) [
            'status' => JobStatus::PENDING,
            'error' => null,
        ];

        DB::shouldReceive('table')
            ->with('papi_jobs')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('where')
            ->with('id', 'job-123')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('first')
            ->once()
            ->andReturn($record);

        $status = $queue->status('job-123');

        expect($status)->toBeInstanceOf(JobStatus::class);
        expect($status->status)->toBe(JobStatus::PENDING);
    });

    it('returns pending status for unknown job ID', function (): void {
        $queue = new LaravelQueue();

        DB::shouldReceive('table')
            ->with('papi_jobs')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('where')
            ->with('id', 'non-existent')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('first')
            ->once()
            ->andReturnNull();

        $status = $queue->status('non-existent');

        expect($status)->toBeInstanceOf(JobStatus::class);
        expect($status->status)->toBe(JobStatus::PENDING);
    });

    it('uses a custom queue name', function (): void {
        $queue = new LaravelQueue(queue: 'ai-tasks');
        $job = new AgentJob(
            agentClass: 'PapiAI\Core\Agent',
            prompt: 'Hello',
            options: [],
        );

        DB::shouldReceive('table')
            ->with('papi_jobs')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('insert')
            ->once()
            ->andReturnTrue();

        Queue::shouldReceive('push')
            ->once()
            ->withArgs(function ($closure, $data, $queueName) {
                return $queueName === 'ai-tasks';
            });

        $queue->dispatch($job);
    });
});
