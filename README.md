# Logres Package

This repository contains the framework-neutral contracts and execution kernel used by the Logres application.

It deliberately has no Laravel, Eloquent, queue, HTTP, Blade, NativePHP, or concrete process dependency.

The current consumer surface is recorded in [PUBLIC-API.md](PUBLIC-API.md).

## Development

```bash
composer install
composer check
```

Local applications consume this package through a Composer path repository during development.

## Execution requests

Logres owns immutable execution request identity, exact prompt preservation, validation, lineage, submission results, and presentation-neutral read models. Hosts provide authentication, authorization integration, identity generation, attachment storage, persistence adapters, and delivery.

An original request has no parent. A correction or child request receives a new identity and references its parent; neither operation mutates the earlier request. A request is accepted only after the configured store returns successfully.
