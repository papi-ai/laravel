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
use PapiAI\Core\Contracts\AgentInterface;
use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Contracts\QueueInterface;
use PapiAI\Core\Exception\ConfigurationException;
use PapiAI\Core\Factory\ProviderRegistry;
use PapiAI\Core\Role;
use PapiAI\Core\Storage\FileConversationStore;
use PapiAI\Laravel\Queue\LaravelQueue;
use PapiAI\Laravel\Storage\EloquentConversationStore;
use PapiAI\Laravel\Tests\TestProvider;
use PapiAI\Laravel\Tests\TestProviderFactory;

describe('PapiServiceProvider', function (): void {
    it('registers the papi singleton with configured provider', function (): void {
        $resolved = $this->app->make('papi');

        expect($resolved)->toBeInstanceOf(ProviderInterface::class);
        expect($resolved)->toBeInstanceOf(TestProvider::class);
    });

    it('builds the default provider through its factory, with the settings it was given', function (): void {
        expect($this->app->make('papi')->settings)->toBe(['api_key' => 'test-key', 'model' => 'test-model']);
    });

    it('builds an installed provider by name when its entry names no driver', function (): void {
        $this->app->instance(ProviderRegistry::class, new ProviderRegistry([new TestProviderFactory()]));
        $this->app['config']->set('papi.providers.test', ['api_key' => 'test-key']);

        expect($this->app->make('papi')->settings)->toBe(['api_key' => 'test-key']);
    });

    it('reads the providers as configured when papi is next resolved', function (): void {
        $this->app->make('papi');
        $this->app->forgetInstance('papi');
        $this->app['config']->set('papi.default', 'other');
        $this->app['config']->set('papi.providers.other', ['driver' => TestProviderFactory::class, 'api_key' => 'other-key']);

        expect($this->app->make('papi')->settings)->toBe(['api_key' => 'other-key']);
    });

    it('reads a provider class given as the driver as the factory beside it', function (): void {
        $this->app['config']->set('papi.providers.test', ['driver' => TestProvider::class, 'api_key' => 'test-key']);

        expect($this->app->make('papi')->settings)->toBe(['api_key' => 'test-key']);
    });

    it('refuses a driver that is not a provider factory', function (): void {
        $this->app['config']->set('papi.providers.test', ['driver' => stdClass::class, 'api_key' => 'test-key']);

        $this->app->make('papi');
    })->throws(ConfigurationException::class, 'Provider "test" names the driver "stdClass", which is not a provider factory and has no "stdClassFactory" beside it.');

    it('refuses a default provider that has no entry', function (): void {
        $this->app['config']->set('papi.default', 'google');

        $this->app->make('papi');
    })->throws(ConfigurationException::class, 'Provider "google" has no entry under papi.providers. Configured: "test".');

    it('binds the core contracts', function (): void {
        expect($this->app->make(ProviderInterface::class))->toBe($this->app->make('papi'));
        expect($this->app->make(AgentInterface::class))->toBe($this->app->make('papi.agent'));
        expect($this->app->make(Agent::class))->toBe($this->app->make('papi.agent'));
        expect($this->app->make(QueueInterface::class))->toBeInstanceOf(LaravelQueue::class);
    });

    it('gives the agent its instructions and output ceiling', function (): void {
        $this->app['config']->set('papi.agent', ['instructions' => 'Answer in one word.', 'max_tokens' => 512]);

        $this->app->make('papi.agent')->run('Capital of Portugal?');

        $sent = $this->app->make('papi');
        expect($sent->lastOptions['maxTokens'])->toBe(512);
        expect($sent->lastMessages[0]->role)->toBe(Role::System);
        expect($sent->lastMessages[0]->content)->toBe('Answer in one word.');
    });

    it('lets the provider choose the model', function (): void {
        $this->app->make('papi.agent')->run('Hi');

        expect($this->app->make('papi')->lastOptions)->not->toHaveKey('model');
    });

    it('ships a config that names every chat provider and leaves their models to them', function (): void {
        $shipped = require __DIR__ . '/../../config/papi.php';

        expect(array_keys($shipped['providers']))->toBe([
            'openai', 'anthropic', 'google', 'grok', 'deepseek', 'mistral', 'groq', 'cohere', 'azure-openai', 'ollama',
        ]);

        foreach ($shipped['providers'] as $settings) {
            expect($settings)->not->toHaveKey('model');
            expect($settings)->not->toHaveKey('driver');
        }
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
