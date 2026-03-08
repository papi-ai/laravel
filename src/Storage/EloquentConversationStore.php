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

namespace PapiAI\Laravel\Storage;

use Illuminate\Support\Facades\DB;
use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Conversation;

/**
 * Database-backed conversation store using Laravel's query builder.
 *
 * Persists conversation history to a database table, allowing conversations
 * to survive across requests and be shared between processes. Uses the
 * configured database connection via the DB facade.
 */
class EloquentConversationStore implements ConversationStoreInterface
{
    /**
     * @param string $table Database table name for storing conversations
     */
    public function __construct(
        private readonly string $table = 'papi_conversations',
    ) {
    }

    /**
     * Save or update a conversation in the database.
     *
     * Uses upsert semantics so both new and existing conversations are handled
     * in a single call. The conversation data is JSON-encoded for storage.
     *
     * @param string       $id           Unique conversation identifier
     * @param Conversation $conversation Conversation instance to persist
     *
     * @return void
     *
     * @throws \JsonException When the conversation data cannot be JSON-encoded
     */
    public function save(string $id, Conversation $conversation): void
    {
        DB::table($this->table)->updateOrInsert(
            ['id' => $id],
            [
                'data' => json_encode($conversation->toArray(), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * Load a conversation from the database by its identifier.
     *
     * Returns null if the conversation does not exist or the stored data
     * cannot be decoded into a valid array.
     *
     * @param string $id Unique conversation identifier
     *
     * @return Conversation|null The hydrated conversation, or null if not found
     *
     * @throws \JsonException When the stored JSON data is malformed
     */
    public function load(string $id): ?Conversation
    {
        $record = DB::table($this->table)->where('id', $id)->first();

        if ($record === null) {
            return null;
        }

        $data = json_decode($record->data, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            return null;
        }

        return Conversation::fromArray($data);
    }

    /**
     * Delete a conversation from the database.
     *
     * @param string $id Unique conversation identifier to remove
     *
     * @return void
     */
    public function delete(string $id): void
    {
        DB::table($this->table)->where('id', $id)->delete();
    }

    /**
     * List conversation identifiers, ordered by most recently updated.
     *
     * @param int $limit Maximum number of conversation IDs to return
     *
     * @return array<int, string> List of conversation identifiers
     */
    public function list(int $limit = 50): array
    {
        return DB::table($this->table)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }
}
