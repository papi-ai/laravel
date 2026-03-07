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

namespace PapiAI\Laravel\Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Mockery;
use PapiAI\Core\Agent;
use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Storage\FileConversationStore;
use PapiAI\Laravel\PapiServiceProvider;
use PapiAI\Laravel\Storage\EloquentConversationStore;

beforeEach(function (): void {
    $this->app = new Container();
    Container::setInstance($this->app);

    $this->app->instance('app', $this->app);

    $this->config = new Repository([
        'papi' => [
            'default' => 'test',
            'providers' => [
                'test' => [
                    'driver' => TestProvider::class,
                    'api_key' => 'test-key',
                    'model' => 'test-model',
                ],
            ],
            'middleware' => [],
            'conversation' => [
                'store' => 'file',
                'path' => sys_get_temp_dir() . '/papi-test',
            ],
        ],
    ]);

    $this->app->instance('config', $this->config);
});

afterEach(function (): void {
    Mockery::close();
    Container::setInstance(null);
});

describe('PapiServiceProvider', function (): void {
    it('registers the papi singleton with configured provider', function (): void {
        $provider = new PapiServiceProvider($this->app);
        $provider->register();

        $resolved = $this->app->make('papi');

        expect($resolved)->toBeInstanceOf(ProviderInterface::class);
        expect($resolved)->toBeInstanceOf(TestProvider::class);
    });

    it('registers the papi.agent singleton', function (): void {
        $provider = new PapiServiceProvider($this->app);
        $provider->register();

        $agent = $this->app->make('papi.agent');

        expect($agent)->toBeInstanceOf(Agent::class);
    });

    it('binds ConversationStoreInterface to FileConversationStore by default', function (): void {
        $provider = new PapiServiceProvider($this->app);
        $provider->register();

        $store = $this->app->make(ConversationStoreInterface::class);

        expect($store)->toBeInstanceOf(FileConversationStore::class);
    });

    it('binds ConversationStoreInterface to EloquentConversationStore when configured', function (): void {
        $this->config->set('papi.conversation.store', 'eloquent');

        $provider = new PapiServiceProvider($this->app);
        $provider->register();

        $store = $this->app->make(ConversationStoreInterface::class);

        expect($store)->toBeInstanceOf(EloquentConversationStore::class);
    });

    it('merges config from package config file', function (): void {
        $provider = new PapiServiceProvider($this->app);
        $provider->register();

        // Config should still have our test values after merge
        expect($this->config->get('papi.default'))->toBe('test');
    });
});

/**
 * A minimal test provider for unit testing the service provider.
 */
class TestProvider implements ProviderInterface
{
    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $defaultModel = '',
    ) {
    }

    public function chat(array $messages, array $options = []): \PapiAI\Core\Response
    {
        return new \PapiAI\Core\Response(text: 'test');
    }

    public function stream(array $messages, array $options = []): iterable
    {
        return [];
    }

    public function supportsTool(): bool
    {
        return false;
    }

    public function supportsVision(): bool
    {
        return false;
    }

    public function supportsStructuredOutput(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'test';
    }
}
