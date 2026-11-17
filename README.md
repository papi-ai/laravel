# PapiAI Laravel Bridge

[![CI](https://github.com/papi-ai/laravel/workflows/CI/badge.svg)](https://github.com/papi-ai/laravel/actions?query=workflow%3ACI) [![Latest Version](https://img.shields.io/packagist/v/papi-ai/laravel.svg)](https://packagist.org/packages/papi-ai/laravel) [![Total Downloads](https://img.shields.io/packagist/dt/papi-ai/laravel.svg)](https://packagist.org/packages/papi-ai/laravel) [![PHP Version](https://img.shields.io/packagist/php-v/papi-ai/laravel.svg)](https://packagist.org/packages/papi-ai/laravel) [![License](https://img.shields.io/packagist/l/papi-ai/laravel.svg)](https://packagist.org/packages/papi-ai/laravel)

Laravel integration for the [PapiAI](https://github.com/papi-ai/papi-core) AI agent library. Provides a service provider, facade, Eloquent conversation store, and queue integration.

## Installation

Install the bridge and at least one provider:

```bash
composer require papi-ai/laravel papi-ai/anthropic
```

The service provider is auto-discovered by Laravel. No manual registration needed.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=papi-config
```

`config/papi.php` holds:

- **`default`**: the entry under `providers` that the agent, the facade and `ProviderInterface` use
- **`providers`**: one entry per provider, holding the settings that provider takes
- **`agent`**: the agent's instructions and output ceiling
- **`middleware`**: middleware classes applied to the agent
- **`conversation`**: file-based or Eloquent-based storage

### Providers

An entry is named after its provider (`openai`, `anthropic`, `google`, `grok`, `deepseek`, `mistral`, `groq`, `cohere`, `azure-openai`, `ollama`) and is built by that provider's own factory, so every setting the provider offers is available and nothing else is accepted. The published file lists all ten with their required settings; install a provider's package before selecting it.

```env
PAPI_PROVIDER=anthropic
ANTHROPIC_API_KEY=your-anthropic-key
```

Leave `model` out to get the provider's current default. Add any other setting the provider takes:

```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => 'claude-opus-5-5',
    'effort' => 'high',
    'replay_thinking' => 'drop_on_edit',
],
```

An entry with a `driver` can take any name, which is how one provider is configured twice. The driver is the provider's factory class; a provider class works too, built by the factory beside it:

```php
'fast' => [
    'driver' => \PapiAI\Anthropic\AnthropicProviderFactory::class,
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => 'claude-haiku-5-5',
],
```

A provider of your own is configured the same way, with a factory that implements `PapiAI\Core\Contracts\ProviderFactoryInterface` (extending `PapiAI\Core\Factory\AbstractProviderFactory` is the short way).

### Agent

```php
'agent' => [
    'instructions' => 'You answer questions about our catalogue.',
    'max_tokens' => 8192,
],
```

## Usage

### Using the Facade

```php
use PapiAI\Laravel\Facades\Papi;

// Simple prompt
$response = Papi::run('What is the capital of France?');
echo $response->text;

// Streaming
foreach (Papi::stream('Tell me a story') as $chunk) {
    echo $chunk->text;
}
```

### Resolving from the Container

| Binding | Resolves to |
|---|---|
| `papi`, `PapiAI\Core\Contracts\ProviderInterface` | The default provider |
| `papi.agent`, `PapiAI\Core\Contracts\AgentInterface`, `PapiAI\Core\Agent` | The agent on the default provider |
| `PapiAI\Core\Contracts\QueueInterface` | `LaravelQueue` |
| `PapiAI\Core\Contracts\ConversationStoreInterface` | The configured conversation store |
| `PapiAI\Core\Factory\ProviderRegistry` | Every installed provider's factory |

```php
use Illuminate\Http\Request;
use PapiAI\Core\Contracts\AgentInterface;

final class AskController
{
    public function __construct(private readonly AgentInterface $agent)
    {
    }

    public function __invoke(Request $request): string
    {
        return $this->agent->run((string) $request->input('question'))->text;
    }
}
```

### Adding Tools

```php
use PapiAI\Laravel\Facades\Papi;

Papi::addTool(new MyCustomTool());
$response = Papi::run('Use my tool to do something');
```

### Middleware

Configure middleware in `config/papi.php`:

```php
'middleware' => [
    \PapiAI\Core\Middleware\LoggingMiddleware::class,
    \PapiAI\Core\Middleware\RetryMiddleware::class,
],
```

Or add middleware at runtime:

```php
use PapiAI\Laravel\Facades\Papi;

Papi::addMiddleware(new RateLimitMiddleware(maxRequests: 10));
```

### Conversation Storage

Switch to Eloquent-based storage in `config/papi.php`:

```php
'conversation' => [
    'store' => 'eloquent',
],
```

The Eloquent store uses the `papi_conversations` table. Create a migration:

```php
Schema::create('papi_conversations', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->json('data');
    $table->timestamp('updated_at')->nullable();
});
```

### Queue Integration

Dispatch agent jobs to Laravel queues. A job names the agent to run by anything the container resolves, such as `PapiAI\Core\Agent` for the configured one or a class of your own:

```php
use PapiAI\Core\Agent;
use PapiAI\Core\AgentJob;
use PapiAI\Core\Contracts\QueueInterface;

$queue = app(QueueInterface::class);

$jobId = $queue->dispatch(new AgentJob(
    agentClass: Agent::class,
    prompt: 'Process this in the background',
));

$status = $queue->status($jobId);

if ($status->isCompleted()) {
    echo $status->result->text;
}
```

The queue tracks jobs in the `papi_jobs` table, and keeps the answer's text (not its usage or tool calls):

```php
Schema::create('papi_jobs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('status');
    $table->text('payload');
    $table->text('result')->nullable();
    $table->text('error')->nullable();
    $table->timestamps();
});
```

## Upgrading to 0.16

- Each provider is built by its own factory, so an entry may hold only the settings its provider takes. Any other key now throws a `ConfigurationException` instead of being ignored.
- A `driver` naming a provider class keeps working when a factory sits beside it, as it does for every official provider. A provider of your own needs a factory implementing `PapiAI\Core\Contracts\ProviderFactoryInterface`, named as the driver.
- Without a published `config/papi.php`, the `openai` entry no longer pins `gpt-4o`: the provider's current default applies. Publish the config and set `model` to keep a specific one.

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- `papi-ai/papi-core` ^0.15.4, and at least one provider package

## License

MIT
