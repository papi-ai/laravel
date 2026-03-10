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
use PapiAI\Core\Conversation;
use PapiAI\Laravel\Storage\EloquentConversationStore;

describe('EloquentConversationStore', function (): void {
    it('saves a conversation using updateOrInsert', function (): void {
        $store = new EloquentConversationStore();
        $conversation = new Conversation();
        $conversation->addUser('Hello');

        DB::shouldReceive('table')
            ->with('papi_conversations')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('updateOrInsert')
            ->once()
            ->withArgs(function (array $conditions, array $values) {
                return $conditions === ['id' => 'conv-1']
                    && isset($values['data'])
                    && isset($values['updated_at']);
            })
            ->andReturnTrue();

        $store->save('conv-1', $conversation);
    });

    it('loads a conversation from the database', function (): void {
        $store = new EloquentConversationStore();
        $conversation = new Conversation();
        $conversation->addUser('Hello');

        $record = (object) [
            'data' => json_encode($conversation->toArray(), JSON_THROW_ON_ERROR),
        ];

        DB::shouldReceive('table')
            ->with('papi_conversations')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('where')
            ->with('id', 'conv-1')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('first')
            ->once()
            ->andReturn($record);

        $loaded = $store->load('conv-1');

        expect($loaded)->toBeInstanceOf(Conversation::class);
        expect($loaded->count())->toBe(1);
    });

    it('returns null for non-existent conversation', function (): void {
        $store = new EloquentConversationStore();

        DB::shouldReceive('table')
            ->with('papi_conversations')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('where')
            ->with('id', 'missing')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('first')
            ->once()
            ->andReturnNull();

        expect($store->load('missing'))->toBeNull();
    });

    it('deletes a conversation', function (): void {
        $store = new EloquentConversationStore();

        DB::shouldReceive('table')
            ->with('papi_conversations')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('where')
            ->with('id', 'conv-2')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('delete')
            ->once()
            ->andReturn(1);

        $store->delete('conv-2');
    });

    it('lists conversation IDs', function (): void {
        $store = new EloquentConversationStore();

        DB::shouldReceive('table')
            ->with('papi_conversations')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('orderByDesc')
            ->with('updated_at')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('limit')
            ->with(50)
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('pluck')
            ->with('id')
            ->once()
            ->andReturn(collect(['conv-a', 'conv-b']));

        $list = $store->list();

        expect($list)->toBe(['conv-a', 'conv-b']);
    });

    it('uses a custom table name', function (): void {
        $store = new EloquentConversationStore(table: 'custom_table');

        DB::shouldReceive('table')
            ->with('custom_table')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('where')
            ->with('id', 'conv-1')
            ->once()
            ->andReturnSelf();

        DB::shouldReceive('first')
            ->once()
            ->andReturnNull();

        expect($store->load('conv-1'))->toBeNull();
    });
});
