# Papi Laravel Bridge -- Development Guidelines

## Project Vision

Papi is the best standalone AI agent library in PHP. This package provides a Laravel bridge with service provider, facade, Eloquent conversation store, and queue integration.

## Quick Reference

```bash
composer lint          # Check code style (PHP CS Fixer, PSR-12)
composer lint:fix      # Auto-fix code style
composer analyse       # Static analysis (Psalm level 4)
composer test          # Run tests (Pest)
composer test:coverage # Run tests with 60% minimum coverage
composer ci            # Run all checks (lint + analyse + test:coverage)
```

## Code Standards

- **PHP 8.2+** with `declare(strict_types=1)` in every file
- **PSR-12** coding style, enforced by PHP CS Fixer
- **Psalm level 4** static analysis must pass with zero errors
- **60% minimum test coverage**, enforced in CI
- **Pest** for testing with describe/it syntax

## Architecture Rules

- **Laravel bridge only** -- thin integration layer, no business logic
- **Service provider** registers papi-core bindings into the Laravel container
- **Facade** provides static proxy to the Agent singleton
- **Config-driven** -- all provider and middleware settings via config/papi.php
- **Conversation stores** -- file-based (default) or Eloquent-based
- **Queue integration** -- dispatch agent jobs via Laravel queues

## Testing

- Use Pest's `describe()` / `it()` syntax
- Use Orchestra Testbench for Laravel integration testing
- Mock facades and database where needed
- Every public method needs test coverage

## Git Workflow

- All checks must pass before committing
- CI runs lint, static analysis, and tests across PHP 8.2-8.5
