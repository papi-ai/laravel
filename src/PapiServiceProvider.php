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

use Illuminate\Support\ServiceProvider;
use PapiAI\Core\Agent;
use PapiAI\Core\Contracts\ConversationStoreInterface;
use PapiAI\Core\Contracts\MiddlewareInterface;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Storage\FileConversationStore;
use PapiAI\Laravel\Storage\EloquentConversationStore;

/**
 * Laravel service provider for PapiAI.
 *
 * Registers AI provider, agent, and conversation store bindings into the
 * Laravel container based on the published `papi.php` configuration file.
 */
class PapiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap PapiAI package services.
     *
     * Publishes the papi configuration file so users can customise
     * provider settings, middleware, and conversation storage.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/papi.php' => config_path('papi.php'),
        ], 'papi-config');
    }

    /**
     * Register PapiAI bindings into the container.
     *
     * Binds the default AI provider as a singleton (`papi`), a pre-configured
     * Agent singleton (`papi.agent`) with middleware, and the conversation
     * store implementation based on config (file or Eloquent).
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/papi.php', 'papi');

        $this->app->singleton('papi', function ($app): ProviderInterface {
            /** @var array{default: string, providers: array<string, array{driver: class-string, api_key: string|null, model: string}>} $config */
            $config = $app['config']['papi'];
            $providerName = $config['default'];
            $providerConfig = $config['providers'][$providerName];

            /** @var class-string<ProviderInterface> $driverClass */
            $driverClass = $providerConfig['driver'];

            return new $driverClass(
                apiKey: $providerConfig['api_key'] ?? '',
                defaultModel: $providerConfig['model'] ?? '',
            );
        });

        $this->app->singleton('papi.agent', function ($app): Agent {
            /** @var ProviderInterface $provider */
            $provider = $app->make('papi');

            /** @var array{default: string, providers: array<string, array{model: string}>, middleware: array<class-string<MiddlewareInterface>>} $config */
            $config = $app['config']['papi'];
            $providerName = $config['default'];
            $model = $config['providers'][$providerName]['model'] ?? '';

            $middleware = [];
            foreach ($config['middleware'] as $middlewareClass) {
                $middleware[] = $app->make($middlewareClass);
            }

            return new Agent(
                provider: $provider,
                model: $model,
                middleware: $middleware,
            );
        });

        $this->app->bind(ConversationStoreInterface::class, function ($app): ConversationStoreInterface {
            /** @var array{conversation: array{store: string, path?: string}} $config */
            $config = $app['config']['papi'];
            $storeType = $config['conversation']['store'];

            if ($storeType === 'eloquent') {
                return new EloquentConversationStore();
            }

            return new FileConversationStore(
                $config['conversation']['path'] ?? storage_path('papi/conversations'),
            );
        });
    }
}
