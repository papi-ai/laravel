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

use PapiAI\Core\Contracts\ProviderFactoryInterface;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Exception\ConfigurationException;
use PapiAI\Core\Factory\ProviderRegistry;

/**
 * The providers configured under papi.providers, each built by its own factory.
 *
 * An entry is the settings its provider's factory takes. The entry's name selects an installed
 * provider, unless the entry names a "driver": a factory class, or a provider class with its
 * factory beside it (PapiAI\Google\GoogleProvider is built by PapiAI\Google\GoogleProviderFactory).
 */
final class ConfiguredProviders
{
    /**
     * @param array<string, array<string, mixed>> $entries Settings by entry name, as in papi.providers
     */
    public function __construct(
        private readonly array $entries,
        private readonly ProviderRegistry $installed,
    ) {
    }

    /**
     * @throws ConfigurationException When the entry is missing, its driver builds no provider, or its settings do not fit
     */
    public function create(string $name): ProviderInterface
    {
        $settings = $this->entries[$name] ?? throw new ConfigurationException(sprintf(
            'Provider "%s" has no entry under papi.providers. Configured: %s.',
            $name,
            implode(', ', array_map(static fn (string $entry): string => '"' . $entry . '"', array_keys($this->entries))),
        ));
        $driver = $settings['driver'] ?? null;
        unset($settings['driver']);

        if (!is_string($driver)) {
            return $this->installed->create($name, $settings);
        }

        return $this->factoryNamedBy($name, $driver)->create($settings);
    }

    private function factoryNamedBy(string $name, string $driver): ProviderFactoryInterface
    {
        foreach ([$driver, $driver . 'Factory'] as $class) {
            if (is_a($class, ProviderFactoryInterface::class, true)) {
                return new $class();
            }
        }

        throw new ConfigurationException(sprintf(
            'Provider "%s" names the driver "%s", which is not a provider factory and has no "%sFactory" beside it.',
            $name,
            $driver,
            $driver,
        ));
    }
}
