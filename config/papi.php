<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | The entry under "providers" that the papi binding, the agent and the
    | Papi facade use.
    |
    */
    'default' => env('PAPI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Each entry holds the settings of one provider, named as its package
    | names it. Install the package (composer require papi-ai/google) before
    | selecting it. Settings each provider also accepts:
    |
    |   model, max_tokens, effort     most providers; leave model out to get
    |                                 the provider's current default
    |   base_url                      openai (a proxy or compatible server)
    |   replay_thinking, compact_at,
    |   clear_tool_results_at         anthropic
    |   api_version                   openai, azure-openai
    |
    | To configure one provider twice, give the second entry a name of your
    | own and a "driver": the provider's factory class, such as
    | \PapiAI\Anthropic\AnthropicProviderFactory::class. A driver is also how
    | a provider of your own, with its own factory, is configured.
    |
    */
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
        ],
        'google' => [
            'api_key' => env('GOOGLE_API_KEY'),
        ],
        'grok' => [
            'api_key' => env('XAI_API_KEY'),
        ],
        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
        ],
        'mistral' => [
            'api_key' => env('MISTRAL_API_KEY'),
        ],
        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
        ],
        'cohere' => [
            'api_key' => env('COHERE_API_KEY'),
        ],
        'azure-openai' => [
            'api_key' => env('AZURE_OPENAI_API_KEY'),
            'endpoint' => env('AZURE_OPENAI_ENDPOINT'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT'),
        ],
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent
    |--------------------------------------------------------------------------
    |
    | The agent behind papi.agent and the Papi facade. A null max_tokens keeps
    | the agent's own ceiling.
    |
    */
    'agent' => [
        'instructions' => '',
        'max_tokens' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware classes to apply to the agent. Each class must implement
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
