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

class EloquentConversationStore implements ConversationStoreInterface
{
    public function __construct(
        private readonly string $table = 'papi_conversations',
    ) {
    }

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

    public function delete(string $id): void
    {
        DB::table($this->table)->where('id', $id)->delete();
    }

    public function list(int $limit = 50): array
    {
        return DB::table($this->table)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }
}
