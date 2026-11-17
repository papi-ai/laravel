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
use PapiAI\Core\Factory\AbstractProviderFactory;
use PapiAI\Core\Factory\Setting;
use PapiAI\Core\Factory\SettingValues;
use PapiAI\Core\Message;
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
        $app['config']->set('papi.providers', [
            'test' => [
                'driver' => TestProviderFactory::class,
                'api_key' => 'test-key',
                'model' => 'test-model',
            ],
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
 * Remembers the settings it was built with and the last request it was sent.
 */
class TestProvider implements ProviderInterface
{
    /** @var list<Message> */
    public array $lastMessages = [];

    /** @var array<string, mixed> */
    public array $lastOptions = [];

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public readonly array $settings = [],
    ) {
    }

    public function chat(array $messages, array $options = []): Response
    {
        $this->lastMessages = $messages;
        $this->lastOptions = $options;

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

final class TestProviderFactory extends AbstractProviderFactory
{
    public function name(): string
    {
        return 'test';
    }

    public function package(): string
    {
        return 'papi-ai/test';
    }

    public function settings(): array
    {
        return [
            new Setting('api_key', true, 'Test key'),
            new Setting('model', false, 'Default model'),
        ];
    }

    protected function build(SettingValues $values): ProviderInterface
    {
        return new TestProvider($this->argumentsGiven([
            'api_key' => $values->stringOrNull('api_key'),
            'model' => $values->stringOrNull('model'),
        ]));
    }
}
