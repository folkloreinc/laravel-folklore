# laravel-folklore

The shared foundation of the Laravel sites built by [Folklore](https://folkloreinc.ca): entities and repositories over Eloquent models, JSON columns with references to other models (through [`folklore/laravel-json-data`](https://github.com/folkloreinc/laravel-json-data)), and opt-in modules for medias, pages, auth and third-party services.

## Versions

| Version | Branch | Status | Requires |
|---|---|---|---|
| 2.x | [`v2.x`](https://github.com/folkloreinc/laravel-folklore/tree/v2.x) (default) | In development, released as alphas | PHP 8.3+, Laravel 12 or 13 |
| 1.x | [`v1.1`](https://github.com/folkloreinc/laravel-folklore/tree/v1.1) | Maintained: fixes and security | PHP 8.3+, Laravel 11 to 13 |

## Installation

2.x, during its pre-releases:

```bash
composer require folklore/laravel-folklore:^2.0@alpha folklore/laravel-json-data:^1.0@alpha
```

`folklore/laravel-json-data` is required by the core. Require it too while it has no stable release: a stability flag doesn't apply to the dependencies of a required package.

The modules are off by default and are turned on in `config/site.php`; each one suggests the packages it needs. See [the architecture](docs/architecture.md#configuration-and-modules).

1.x:

```bash
composer require folklore/laravel-folklore:^1.1
```

## Documentation

- [docs/](docs/README.md): architecture, decisions, the plan of 2.0 ([roadmap](docs/roadmap.md)) and the [upgrade guide](docs/upgrade.md) from 1.1.
- [CHANGELOG](CHANGELOG.md)
- [AGENTS.md](AGENTS.md): how to work on this repository.

## License

MIT
