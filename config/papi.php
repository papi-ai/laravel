<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | The default AI provider to use when creating agents. You can switch
    | providers at runtime or configure multiple providers below.
    |
    */
    'default' => env('PAPI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Configure each AI provider with its driver class, API key, and default
    | model. Add additional providers by following the same pattern.
    |
    */
    'providers' => [
        'openai' => [
            'driver' => \PapiAI\OpenAI\OpenAIProvider::class,
            'api_key' => env('OPENAI_API_KEY'),
            'model' => 'gpt-4o',
        ],
        'anthropic' => [
            'driver' => \PapiAI\Anthropic\AnthropicProvider::class,
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => 'claude-sonnet-4-20250514',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware classes to apply to all agents. Each class must implement
    | PapiAI\Core\Contracts\MiddlewareInterface.
    |
    */
    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Conversation Storage
    |--------------------------------------------------------------------------
    |
    | Configure how conversations are stored. Supported stores: "file", "eloquent".
    |
    */
    'conversation' => [
        'store' => 'file',
        'path' => storage_path('papi/conversations'),
    ],
];
