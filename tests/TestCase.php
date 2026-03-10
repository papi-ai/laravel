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

namespace PapiAI\Laravel\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Response;
use PapiAI\Laravel\PapiServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [PapiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('papi.default', 'test');
        $app['config']->set('papi.providers.test', [
            'driver' => TestProvider::class,
            'api_key' => 'test-key',
            'model' => 'test-model',
        ]);
        $app['config']->set('papi.middleware', []);
        $app['config']->set('papi.conversation.store', 'file');
        $app['config']->set('papi.conversation.path', sys_get_temp_dir() . '/papi-test');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}

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

    public function chat(array $messages, array $options = []): Response
    {
        return new Response(text: 'test');
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
