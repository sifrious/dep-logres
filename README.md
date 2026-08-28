# Logres Package

This repository contains the framework-neutral contracts and execution kernel used by the Logres application.

It deliberately has no Laravel, Eloquent, queue, HTTP, Blade, NativePHP, or concrete process dependency.

The current consumer surface is recorded in [PUBLIC-API.md](PUBLIC-API.md).

## Development

```bash
composer install
composer check
```

The local application at `/Users/mme/Projects/logres` consumes this package through a Composer path repository.
