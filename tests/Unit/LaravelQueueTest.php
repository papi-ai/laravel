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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PapiAI\Core\Agent;
use PapiAI\Core\AgentJob;
use PapiAI\Core\Contracts\QueueInterface;
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

    it('returns the answer of a completed job', function (): void {
        $record = (object) [
            'status' => JobStatus::COMPLETED,
            'result' => json_encode('Lisbon'),
            'error' => null,
        ];

        DB::shouldReceive('table')->with('papi_jobs')->once()->andReturnSelf();
        DB::shouldReceive('where')->with('id', 'job-123')->once()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn($record);

        $status = (new LaravelQueue())->status('job-123');

        expect($status->isCompleted())->toBeTrue();
        expect($status->result?->text)->toBe('Lisbon');
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

    describe('on a sync queue', function (): void {
        beforeEach(function (): void {
            $this->app['config']->set('queue.default', 'sync');
            Schema::create('papi_jobs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('status');
                $table->text('payload');
                $table->text('result')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
            });
        });

        it('runs the job on the agent it names and records the answer', function (): void {
            $queue = $this->app->make(QueueInterface::class);

            $status = $queue->status($queue->dispatch(new AgentJob(Agent::class, 'Capital of Portugal?')));

            expect($status->isCompleted())->toBeTrue();
            expect($status->result?->text)->toBe('test');
        });

        it('records why a job failed', function (): void {
            $queue = $this->app->make(QueueInterface::class);

            $status = $queue->status($queue->dispatch(new AgentJob('App\\Agents\\Missing', 'Hi')));

            expect($status->isFailed())->toBeTrue();
            expect($status->error)->toContain('App\\Agents\\Missing');
        });
    });
});
