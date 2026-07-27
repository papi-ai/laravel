# Laravel Bridge

Laravel integration for PapiAI. Provides a service provider, facade, Eloquent conversation store, and queue integration.

## Installation

```bash
composer require papi-ai/laravel papi-ai/openai
```

The service provider is auto-discovered by Laravel. No manual registration needed.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=papi-config
```

This creates `config/papi.php` where you can configure:

- **Default provider** -- which AI provider to use (`openai`, `anthropic`, etc.)
- **Provider settings** -- API keys, models, and driver classes
- **Middleware** -- middleware classes applied to all agents
- **Conversation storage** -- file-based or Eloquent-based

### Environment Variables

```env
PAPI_PROVIDER=openai
OPENAI_API_KEY=your-openai-key
ANTHROPIC_API_KEY=your-anthropic-key
```

## Facade

```php
use PapiAI\Laravel\Facades\Papi;

// Simple prompt
$response = Papi::run('What is the capital of France?');
echo $response->text;

// Streaming
foreach (Papi::stream('Tell me a story') as $chunk) {
    echo $chunk->text;
}

// Add tools at runtime
Papi::addTool(new MyCustomTool());

// Add middleware at runtime
Papi::addMiddleware(new RateLimitMiddleware(maxRequests: 10));
```

## Container Bindings

```php
// Get the configured provider
$provider = app('papi');

// Get the pre-configured agent
$agent = app('papi.agent');
$response = $agent->run('Hello!');
```

## Middleware Configuration

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

## Conversation Storage

### File-based (Default)

The default storage uses JSON files. No additional configuration required.

### Eloquent Storage

Switch to Eloquent-based storage in `config/papi.php`:

```php
'conversation' => [
    'store' => 'eloquent',  // or 'file'
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

## Queue Integration

Dispatch agent jobs to Laravel queues:

```php
use PapiAI\Laravel\Queue\LaravelQueue;
use PapiAI\Core\AgentJob;

$queue = app(LaravelQueue::class);

$jobId = $queue->dispatch(new AgentJob(
    agentClass: MyAgent::class,
    prompt: 'Process this in the background',
));

$status = $queue->status($jobId);
```

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- `papi-ai/papi-core` ^0.14
