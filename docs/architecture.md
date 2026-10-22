# Architecture of 2.0

Design of `laravel-folklore` 2.0. Each choice is recorded in [decisions/](decisions/); the 1.x it starts from is in [current-state.md](current-state.md). Class names that 1.x doesn't have yet (`QueryContextResolver`, `ModuleRequirementMissing`, the module namespaces) are proposals until the contracts land in phase 0 ([roadmap.md](roadmap.md)). Details marked **Proposed** go beyond the decisions and are open to review.

## Goals

- **The shared foundation of the Folklore Laravel sites**, built on `laravel-mediatheque` 2, `laravel-image` 2 and Panneau 5.
- **A core with no dependency on the other Folklore packages**, that a new project can adopt alone: entities, repositories, the global query context and `JsonDataCast`.
- **Opt-in modules** in the same package, that cost nothing when they are off.
- **Cheap upgrades:** the sites on 1.1 upgrade without rewriting their repositories and entities. The 1.x API is kept, deprecated, until 3.0.
- **Typed new code**, checked by Larastan at its maximum level, next to the 1.x signatures that sites override.

## Boundaries

| Concern | Owner |
|---|---|
| Entities, repositories, query context, JSON references | This package, core |
| Media storage, ingest, renditions, `media://` references, usage graph | `laravel-mediatheque` 2. This package's medias module adapts it to entities and JSON data |
| Image presets and URLs | `laravel-image` 2, through the medias module |
| Admin UI and its protocol | Panneau 5. This package ships a plugin: a repository adapter and page fields |
| Locales and localized routes | `laravel-locale`, used by the pages module |
| Tenancy, users' roles, policies | The application. The package passes a query context and calls handlers the application configures |

## Overview

```text
folklore/laravel-folklore
├── Core (illuminate/* only)
│   ├── Contracts: Entity, HasModel, ToEntity, Entities (repository), ReferenceHandler
│   ├── Entities: optional typed base entity
│   ├── Repositories: Entities base class, typed query, global query context
│   └── Eloquent: JsonDataCast, JsonData registry, default reference handler
└── Modules (opt-in, in config/site.php)
    ├── medias      → laravel-mediatheque 2, laravel-image 2
    ├── pages       → laravel-locale, eloquent-sluggable
    ├── auth        → laravel/fortify
    ├── panneau     → laravel-panneau 5
    └── services    → Customer.io, Google, PubNub, response macros, composers, commands
```

## Configuration and modules

Modules are enabled in `config/site.php`, which 1.x already uses (`site.medias.download`). The shape is **proposed**:

```php
'modules' => [
    'medias' => true,
    'pages' => false,
    'auth' => false,
    'panneau' => false,
    'customerio' => false,
    'google' => false,
    'pubnub' => false,
],
```

- Each module has a service provider that the package's provider registers only when the module is on.
- A module checks that the packages it needs are installed when it boots, and throws a `ModuleRequirementMissing` exception that names the package to require.
- With every module off, the package registers the core bindings and nothing else: no route, macro, middleware, listener, auth provider or global override ([decisions/0002](decisions/0002-core-and-modules.md)).
- `folklore:upgrade` writes the modules an upgraded site uses into its config.

## Entities

- **Contracts keep their 1.x names and methods.** `Entity` (`id(): string`), `HasModel` and `ToEntity` stay, but `Entity` no longer extends Panneau's `ResourceItem` ([decisions/0003](decisions/0003-evolve-in-place.md)).
- **An optional typed base entity** for new code: readonly, built from a model, with `id()` as the model's route key and `getModel()` for the edges that need a model (Fortify, Sanctum). The pilot's prototype of the core is the starting point. The 1.x entities don't extend it, so their protected properties (`$model`, `$data`, `$metadata`, `$sizes`, `$files`) keep working.
- `HasTypedEntity`, `to_entity()`, `to_id()` and `SerializesEntityModel` stay, with the same behaviour and payload.
- The `make:entity` and `make:repository` generators produce typed classes on the 2.0 bases.

## Repositories

`Repositories\Entities` stays the base class, with its protected API ([current-state.md](current-state.md#repositories)).

- The `Entities` contract no longer extends Panneau's `Repository`.
- **A typed query** is added next to `get($params, $page, $count)`. It is close to Panneau 5's `ResourceQuery`: filters, sorts, search, pagination, `ids`, `except`. It maps to the same `buildQueryFromParams()`, so a site's overrides apply to both. The Panneau plugin's adapter is built on it.
- Existing methods get PHPDoc types, checked by Larastan; their signatures don't change.
- Repositories list their JSON columns (`$jsonAttributes`), not only `data`, and can load the references of a whole result (`withJsonReferences()`) ([json-data.md](json-data.md#repositories)).
- **Proposed:** `findById()`, `update()` and `destroy()` apply the global query context, as `get()` does. In 1.x they ignore it, which defeats tenant scoping; applying it changes what they return for a scoped repository, so it would be listed in the upgrade guide.
- A repository bound by the package or by `RepositoriesServiceProvider` stays a `bind()`, not a singleton.

### Global query context

([decisions/0004](decisions/0004-global-query-context.md))

```php
$games->setGlobalQuery(['contest' => $contest->id]);          // 1.x: changes this instance
$scoped = $games->withGlobalQuery(['contest' => $contest->id]); // a copy; $games is unchanged

// A default context, applied to every repository without an explicit one
$this->app->bind(QueryContextResolver::class, CurrentTenantContext::class);
```

The context is merged into the params, as in 1.x: defaults, then the resolver's context, then the global query, then the call's params. Panneau 5's `scope()` maps to `withGlobalQuery()` in the plugin.

## JSON data

`JsonDataCast` keeps its 1.x declaration (`getJsonDataRelations()`) and stored format (`{key}://{identifier}`). 2.0 adds a global registry of reference keys, handlers per key, batch resolution, memoization, a reference listing, a recursive wildcard and a rewrite command. It is specified in [json-data.md](json-data.md), with [decisions 0005 to 0008](decisions/).

## Medias module

On `laravel-mediatheque` 2 and `laravel-image` 2 ([decisions/0008](decisions/0008-media-references.md)).

- **Alpha 1:** the `media` and `medias` reference keys and their handler, `MediaReferences`.
- **Alpha 2:**
  - `Entities\Media`, `Image` and `ImageSize` delegate to the mediatheque's contracts and to `Image::for($media)->preset(...)->variant()`. Their protected properties are kept.
  - `MediaResource` delegates to the mediatheque's canonical resource, with its `legacy` shape for upgraded sites.
  - `createFromFile` and `createFromPath` go through `Mediatheque::ingest()`. Downloading from a URL, with its host and size limits, moves to `laravel-mediatheque` ([laravel-mediatheque#1](https://github.com/folkloreinc/laravel-mediatheque/issues/1)).
  - Sites declare their models in `config('mediatheque.models')`; the package no longer rebinds the mediatheque's model contracts.

## Pages module

([decisions/0009](decisions/0009-pages-and-blocks.md))

- Blocks are stored in the page's JSON; `blocks_pivot` goes away and `folklore:upgrade` converts existing data.
- The `slug_{locale}` columns stay. Route names (`home`, `page`, `page_with_parent`) are configurable.
- A type registry maps page and block types to entity classes, in `config/site.php` or in code. `HasTypedEntity` reads `$entitiesByType` on the model first, then the registry.

## Auth module

([decisions/0010](decisions/0010-users-and-auth.md)) The `repository` auth driver, the `User` entity with two-factor authentication, and the Fortify callbacks boot only when the module is enabled.

## Panneau 5 plugin

([decisions/0011](decisions/0011-panneau-plugin.md)) An adapter for Panneau's `ResourceRepository` on the typed query, `scope()` on the query context, and the `Page`, `Pages`, `Blocks` and `PageSlug` fields with their JavaScript. It ships before the end of Panneau 5's betas.

## Other modules

([decisions/0012](decisions/0012-other-modules.md)) Customer.io, Google (Places on Laravel's HTTP client), PubNub, the view composers and `routeForReactRouter`, the `csv` and `pixel` macros, `daemon:restart`. The site manager, organisations, PubSubHubbub and the unused contracts are removed. The global paginator resolver override is removed.

## Compatibility and deprecations

([decisions/0003](decisions/0003-evolve-in-place.md))

- What 2.0 replaces stays on the same class, marked `@deprecated`, and logs a deprecation on Laravel's `deprecations` channel when it is used.
- The 1.1 test suite runs on `v2.x` as the compatibility suite, except what covers Panneau 1.x and the removed modules.
- `folklore:upgrade --check` lists what a site uses that changed: removed modules, unmapped JSON keys, block data to convert, deprecated macros. `folklore:upgrade` enables the site's modules and converts data.
- Rector rules rename what moved.
- 3.0 removes the deprecated API.

## Testing

- The core's tests run with `illuminate/*` only. Each module's tests run with its dependencies, and check that the module stays off when they are missing.
- Contract tests for the base entity and repository, that sites can run against their own classes.
- `JsonDataCast` keeps the 1.1.8 characterization tests, on SQLite and MySQL.

## Package layout

**Proposed**, settled with the tooling in phase 0. PSR-4, `Folklore\` → `src/`, replacing 1.x's PSR-0 `src/Folklore/`. The paths change, the class names don't.

```text
src/
├── Contracts/            core contracts
├── Entities/             1.x entities, the typed base entity
├── Repositories/         Entities base class, typed query, context
├── Eloquent/             JsonDataCast, JsonData, JsonReference, handlers
├── Medias/               medias module
├── Pages/                pages module
├── Auth/                 auth module
├── Panneau/              Panneau 5 plugin
├── Services/             Customer.io, Google
├── Support/              helpers, concerns
└── ServiceProvider.php
```

1.x classes that move into a module keep a class alias at their 1.x name until 3.0.

## Dependencies

| Package | 2.0 |
|---|---|
| `illuminate/*` `^12.0\|^13.0` | Required |
| `folklore/laravel-mediatheque ^2.0`, `folklore/laravel-image ^2.0` | Suggested (medias module) |
| `folklore/laravel-locale ^8.1`, `cviebrock/eloquent-sluggable` | Suggested (pages module) |
| `laravel/fortify` | Suggested (auth module) |
| `folklore/laravel-panneau ^5.0` | Suggested (Panneau plugin) |
| `guzzlehttp/guzzle`, `parsecsv/php-parsecsv`, `pubnub/pubnub` | Suggested (services) |
| `google/apiclient` | Removed |
| `folklore/laravel-panneau` 1.x | Removed |

## Supported versions

PHP 8.3+ and Laravel 12+ ([decisions/0001](decisions/0001-supported-versions.md), proposed).
