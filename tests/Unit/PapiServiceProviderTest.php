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

use PapiAI\Core\Agent;
use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Storage\FileConversationStore;
use PapiAI\Laravel\Storage\EloquentConversationStore;
use PapiAI\Laravel\Tests\TestProvider;

describe('PapiServiceProvider', function (): void {
    it('registers the papi singleton with configured provider', function (): void {
        $resolved = $this->app->make('papi');

        expect($resolved)->toBeInstanceOf(ProviderInterface::class);
        expect($resolved)->toBeInstanceOf(TestProvider::class);
    });

    it('registers the papi.agent singleton', function (): void {
        $agent = $this->app->make('papi.agent');

        expect($agent)->toBeInstanceOf(Agent::class);
    });

    it('binds ConversationStoreInterface to FileConversationStore by default', function (): void {
        $store = $this->app->make(ConversationStoreInterface::class);

        expect($store)->toBeInstanceOf(FileConversationStore::class);
    });

    it('binds ConversationStoreInterface to EloquentConversationStore when configured', function (): void {
        $this->app['config']->set('papi.conversation.store', 'eloquent');

        $store = $this->app->make(ConversationStoreInterface::class);

        expect($store)->toBeInstanceOf(EloquentConversationStore::class);
    });

    it('merges config from package config file', function (): void {
        expect(config('papi.default'))->toBe('test');
    });
});
