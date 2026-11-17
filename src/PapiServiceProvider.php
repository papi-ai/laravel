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

namespace PapiAI\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PapiAI\Core\Agent;
use PapiAI\Core\Contracts\AgentInterface;
use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Contracts\MiddlewareInterface;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Contracts\QueueInterface;
use PapiAI\Core\Factory\ComposerDiscovery;
use PapiAI\Core\Factory\ProviderRegistry;
use PapiAI\Core\Storage\FileConversationStore;
use PapiAI\Laravel\Queue\LaravelQueue;
use PapiAI\Laravel\Storage\EloquentConversationStore;

/**
 * Laravel service provider for PapiAI.
 *
 * Binds the default provider (`papi`, ProviderInterface), an agent on it (`papi.agent`,
 * AgentInterface, Agent), the queue and the conversation store, all from `config/papi.php`.
 */
class PapiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/papi.php' => config_path('papi.php'),
        ], 'papi-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/papi.php', 'papi');

        $this->bindProviders();
        $this->bindAgent();
        $this->bindQueueAndConversationStore();
    }

    private function bindProviders(): void
    {
        $this->app->singleton(ProviderRegistry::class, static fn (): ProviderRegistry => new ProviderRegistry(new ComposerDiscovery()));

        $this->app->bind(ConfiguredProviders::class, static function (Application $app): ConfiguredProviders {
            /** @var array<string, array<string, mixed>> $entries */
            $entries = $app->make('config')->get('papi.providers', []);

            return new ConfiguredProviders($entries, $app->make(ProviderRegistry::class));
        });

        $this->app->singleton('papi', static function (Application $app): ProviderInterface {
            /** @var string $default */
            $default = $app->make('config')->get('papi.default');

            return $app->make(ConfiguredProviders::class)->create($default);
        });
        $this->app->alias('papi', ProviderInterface::class);
    }

    private function bindAgent(): void
    {
        $this->app->singleton('papi.agent', static function (Application $app): Agent {
            /** @var array{instructions?: string|null, max_tokens?: int|null} $agent */
            $agent = $app->make('config')->get('papi.agent', []);
            /** @var list<class-string<MiddlewareInterface>> $middlewareClasses */
            $middlewareClasses = $app->make('config')->get('papi.middleware', []);

            return new Agent(...array_filter([
                'provider' => $app->make('papi'),
                'model' => '',
                'instructions' => $agent['instructions'] ?? null,
                'maxTokens' => $agent['max_tokens'] ?? null,
                'middleware' => array_map(static fn (string $class): MiddlewareInterface => $app->make($class), $middlewareClasses),
            ], static fn (mixed $argument): bool => $argument !== null));
        });
        $this->app->alias('papi.agent', AgentInterface::class);
        $this->app->alias('papi.agent', Agent::class);
    }

    private function bindQueueAndConversationStore(): void
    {
        $this->app->bind(QueueInterface::class, LaravelQueue::class);

        $this->app->bind(ConversationStoreInterface::class, static function (Application $app): ConversationStoreInterface {
            /** @var array{store: string, path?: string} $conversation */
            $conversation = $app->make('config')->get('papi.conversation');

            if ($conversation['store'] === 'eloquent') {
                return new EloquentConversationStore();
            }

            return new FileConversationStore($conversation['path'] ?? storage_path('papi/conversations'));
        });
    }
}
