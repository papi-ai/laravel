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

namespace PapiAI\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use PapiAI\Core\Agent;
use PapiAI\Core\Response;

/**
 * @method static Response run(string $prompt, array $options = [])
 * @method static iterable stream(string $prompt, array $options = [])
 * @method static iterable streamEvents(string $prompt, array $options = [])
 * @method static Agent addTool(\PapiAI\Core\Contracts\ToolInterface $tool)
 * @method static Agent addMiddleware(\PapiAI\Core\Contracts\MiddlewareInterface $middleware)
 *
 * @see \PapiAI\Core\Agent
 */
class Papi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'papi.agent';
    }
}
