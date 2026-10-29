# Architecture of 2.0

Design of `laravel-folklore` 2.0. Each choice is recorded in [decisions/](decisions/); the 1.x it starts from is in [current-state.md](current-state.md). New class names are settled in [decisions/0014](decisions/0014-names.md); the typed query, the typed base entity and the module namespaces are named with the contracts ([roadmap.md](roadmap.md)). Details marked **Proposed** go beyond the decisions and are open to review.

## Goals

- **The shared foundation of the Folklore Laravel sites**, built on `laravel-mediatheque` 2, `laravel-image` 2 and Panneau 5.
- **A core with no dependency on the other Folklore packages**, that a new project can adopt alone: entities, repositories, the global query context and JSON columns. The JSON columns live in their own package, `folklore/laravel-json-data`, which the core requires and which depends on no other Folklore package ([decisions/0021](decisions/0021-json-data-package.md)).
- **Opt-in modules** in the same package, that cost nothing when they are off.
- **Cheap upgrades:** the sites on 1.1 upgrade without rewriting their repositories and entities. The 1.x API is kept, deprecated, until 3.0.
- **Typed new code**, checked by Larastan at its maximum level, next to the 1.x signatures that sites override.

## Boundaries

| Concern | Owner |
|---|---|
| Entities, repositories, query context | This package, core |
| JSON references, and the schema of JSON columns | `folklore/laravel-json-data`, required by the core ([decisions/0021](decisions/0021-json-data-package.md)). This package adds the entities, the repositories' JSON columns and media references |
| Media storage, ingest, renditions, `media://` references, usage graph | `laravel-mediatheque` 2. This package's medias module adapts it to entities and JSON data |
| Image presets and URLs | `laravel-image` 2, through the medias module |
| Admin UI and its protocol | Panneau 5. This package ships a plugin: a repository adapter and page fields |
| Locales and localized routes | `laravel-locale`, used by the pages module |
| Tenancy, users' roles, policies | The application. The package passes a query context and calls handlers the application configures |

## Overview

```text
folklore/laravel-folklore
├── Core (illuminate/* and folklore/laravel-json-data)
│   ├── Contracts: Entity, HasModel, ToEntity, Entities (repository)
│   ├── Entities: optional typed base entity
│   ├── Repositories: Entities base class, typed query, global query context
│   └── Eloquent: the 1.x JSON API on laravel-json-data (cast subclass with macros, 1.x declarations, aliases)
└── Modules (opt-in, in config/site.php)
    ├── medias      → laravel-mediatheque 2, laravel-image 2
    ├── pages       → laravel-locale, eloquent-sluggable
    ├── auth        → laravel/fortify
    ├── panneau     → laravel-panneau 5
    └── services    → Customer.io, Google, PubNub, response macros, composers, commands
```

## Configuration and modules

Modules are turned on in `config/site.php`, which 1.x already uses (`site.medias.download`). The package merges its defaults (`config/site.php` in the package), where every module is off:

```php
'modules' => [
    'medias' => false,        // Medias repository, mediatheque model bindings
    'pages' => false,         // Pages and blocks repositories
    'auth' => false,          // `repository` auth provider, users repository, Fortify callbacks, `users:create`
    'organisations' => false, // Organisations repository (removed in 2.0)
    'sites' => false,         // Site manager (removed in 2.0)
    'http' => false,          // `routeForReactRouter`, `pixel` and `csv` responses, OffsetPaginator's page resolver
    'assets' => false,        // `assets:view`, and LocalMiddleware in the local environment (deprecated)
    'customerio' => false,    // Customer.io client and mail transport, when `services.customerio` is set
    'google' => false,        // Google Maps, Places and Drive
    'pubnub' => false,        // `pubnub` broadcast driver
    'pubsubhubbub' => false,  // PubSubHubbub client and commands, when `pubsubhubbub` is set (removed in 2.0)
],
```

- With every module off, the package registers the core and nothing else: the repositories of `config('app.repositories')`, `daemon:restart`, and, in the local environment, the `make:*` generators and the publishing of the migrations. No binding of its own repositories, macro, middleware, auth provider, broadcast driver or global override ([decisions/0002](decisions/0002-core-and-modules.md)).
- A module that is on checks, when the package registers, that the packages it needs are installed (`ServiceProvider::MODULES`), and throws `Exceptions\ModuleRequirementMissing` with the package to require:

  | Module | Needs |
  |---|---|
  | `medias` | `folklore/laravel-mediatheque` 2, `folklore/laravel-image` 2 |
  | `pages` | `folklore/laravel-mediatheque` 2 (its models use `HasMedias`), `cviebrock/eloquent-sluggable`, `folklore/laravel-locale` |
  | `auth` | `laravel/fortify` |
  | `pubnub` | `pubnub/pubnub` |

  Google Places still needs `google/apiclient` when it is used, until it moves to Laravel's HTTP client.
- The modules use parts of Laravel that the core doesn't require (HTTP, routing, auth, mail, broadcasting, views, and `Foundation` for the auth module's `User` model): a Laravel application has them.
- For now, the package's service provider registers each module's part. A module gets its own service provider when it gets its namespace, with its alpha.
- The panneau module, for the Panneau 5 plugin, comes with its repository adapter in the slice "Panneau 5 adapter and media entities", and gets its page fields with the pages module, in the slice "Pages" ([roadmap.md](roadmap.md)). The Panneau 1.x fields and resources are removed.
- `folklore:upgrade` writes the modules an upgraded site uses into its config. Until it exists (beta), an upgraded site turns them on by hand.

## Entities

- **Contracts keep their 1.x names and methods.** `Entity` (`id(): string`), `HasModel` and `ToEntity` stay, but `Entity` no longer extends Panneau's `ResourceItem` ([decisions/0003](decisions/0003-evolve-in-place.md)).
- **An optional typed base entity** for new code, `Entities\ModelEntity`: built from a model, which it keeps in a readonly property, with `id()` as the model's entity key ([decisions/0015](decisions/0015-entity-key.md)) and `getModel()` for the edges that need a model (Fortify, Sanctum). It is the pilot's prototype, under the same name; the prototype's `id()` read the route key. The 1.x entities don't extend it, so their protected properties (`$model`, `$data`, `$metadata`, `$sizes`, `$files`) keep working.
- `HasEntityKey`, on models, says which column holds the entity's public id, for the models whose public id isn't their key ([decisions/0015](decisions/0015-entity-key.md)).
- `HasModel`, `ToEntity` and the `Entities` repository contract are generic (`HasModel<TModel>`, `ToEntity<TEntity>`, `Entities<TEntity>`), with defaults, so 1.x classes that don't say their types keep passing Larastan.
- `HasTypedEntity`, `to_entity()`, `to_id()` and `SerializesEntityModel` stay, with the same behaviour and payload.
- The `make:entity` and `make:repository` generators produce typed classes on the 2.0 bases.

### Review against the pilot's prototype

The pilot writes its entities and repositories on a prototype of the core, to be replaced by this package ([#56](https://github.com/folkloreinc/laravel-folklore/issues/56)). Reviewed in phase 0:

| Prototype | 2.0 | Swap |
|---|---|---|
| `Contracts\Entities\Entity` (`id(): string`) | Same | Ready |
| `HasModel<TModel>`, `ToEntity<TEntity>` (`toEntity(): Entity`) | Same, generic; `toEntity()` returns `?Entity`, which a model may narrow | Ready |
| `Contracts\Repositories\Entities<TEntity>` with `findById()` only | The 1.x contract, generic: also `get()`, `count()`, `create()`… | Ready for repositories whose methods match its signatures: they extend the base class, which implements them. A repository that declares `create(array $data)` or `update(Model $model, array $data)` doesn't load, since PHP refuses a narrower parameter type: it renames those methods, or keeps the contract's signatures and types them in PHPDoc. The contract stays as it is ([#83](https://github.com/folkloreinc/laravel-folklore/issues/83), closed) |
| `Entities\ModelEntity<TModel>`, `id()` = route key | Same, `id()` = the model's entity key (`HasEntityKey`), or its key | Ready once the pilot's models implement `HasEntityKey` |
| `Repositories\Entities<TModel, TEntity>`: `newModel()`, `newQuery(): Builder`, `toEntity()` | The 1.x base class, not generic; `newQuery()` untyped, entities built by `getEntityFromModel()` | 2.0.0-alpha.1: the base class gets `@template TModel, TEntity` with defaults |
| `findById()` by the model's route key (the public uid) | `findById()` by the model's entity key, or its key ([decisions/0015](decisions/0015-entity-key.md)) | 2.0.0-alpha.1, in the base class; the pilot implements `HasEntityKey` in the trait that gives its models their uid |

### Review against `laravel-mediatheque` 2

- `MediaReference` (`media://` by uid, `medias://` by id, with `SCHEME` and `LEGACY_SCHEME`) fits `MediaReferences`: it parses the values it prepares. `MediaReferences::resolveMany()` resolves the identifiers of its key in one query on the key's column, with the media's files, as `Mediatheque::resolveMany()` does, but without the facade, so that it works under `Mediatheque::fake()` ([#98](https://github.com/folkloreinc/laravel-folklore/issues/98)).
- `JsonData::parse()` is more lenient than `MediaReference::tryParse()`, which checks the uid's format. The handler checks it; the registry only checks the key.
- The usage graph (`HasMedias`, `usages()`) shipped in the mediatheque's 2.0.0-alpha.6. JSON references sync into it with `syncMedias()`, under one handle, `json`, for every JSON column of a model ([json-data.md](json-data.md#syncing-media-references)). Settled in 2.0.0-alpha.1.

## Repositories

`Repositories\Entities` stays the base class, with its protected API ([current-state.md](current-state.md#repositories)).

- The `Entities` contract no longer extends Panneau's `Repository`.
- **A typed query** is added next to `get($params, $page, $count)`: `queryEntities(EntitiesQuery $query): LengthAwarePaginator` (not `query()`, which 1.x repositories may already have), with `withGlobalQuery()`, in the `QueriesEntities` contract. `Repositories\Query\EntitiesQuery` is close to Panneau 5's `ResourceQuery`: filters (the 1.x params), search, sorts (`Sort`, `SortDirection`), `ids`, `except`, page and page size. It maps to the same `buildQueryFromParams()`, so a site's overrides apply to both. The Panneau plugin's adapter is built on it. The base class implements it. The `Entities` contract doesn't extend it, so that a site class that implements `Entities` without the base class keeps loading.
- Existing methods get PHPDoc types, checked by Larastan; their signatures don't change.
- Repositories list their JSON columns (`$jsonAttributes`), not only `data`, and can load the references of a whole result (`withJsonReferences()`) ([json-data.md](json-data.md#repositories)).
- `findById()`, `update()`, `destroy()` and `valueById()` apply the query context, as `get()` does, but not the default params ([decisions/0017](decisions/0017-finds-apply-the-query-context.md)).
- Repositories find by the model's entity key (`HasEntityKey::getEntityKeyName()`), or by its key name when the model doesn't implement the contract ([decisions/0015](decisions/0015-entity-key.md)).
- A repository bound by the package or by `RepositoriesServiceProvider` stays a `bind()`, not a singleton.

### Global query context

([decisions/0004](decisions/0004-global-query-context.md))

```php
$games->setGlobalQuery(['contest' => $contest->id]);          // 1.x: changes this instance
$scoped = $games->withGlobalQuery(['contest' => $contest->id]); // a copy; $games is unchanged

// A default context, applied to every repository without an explicit one
// QueryContextResolver::resolve(Entities $repository): array
$this->app->bind(QueryContextResolver::class, CurrentTenantContext::class);  // resolved for each query
```

The context is merged into the params: for lists, defaults, then the resolver's context, then the global query, then the call's params; for finds, the same without the defaults. `withGlobalQuery()` merges into the repository's global query. Panneau 5's `scope()` maps to `withGlobalQuery()` in the plugin.

## JSON data

`JsonDataCast` keeps its 1.x declaration (`getJsonDataRelations()`) and stored format (`{key}://{identifier}`). 2.0 adds a global registry of reference keys, handlers per key, batch resolution, memoization, a reference listing, a recursive wildcard, a rewrite command and a schema of JSON columns ([decisions 0005 to 0008](decisions/), [0020](decisions/0020-json-schema.md)).

It lives in `folklore/laravel-json-data` since 2.0.0-alpha.4 ([decisions/0021](decisions/0021-json-data-package.md)), which specifies it; [json-data.md](json-data.md) covers what this package adds. The package declares columns only with `jsonDataSchema()`. The 1.x names stay until 3.0: aliases, and a subclass of the cast for the macros. This package keeps the 1.x declarations (`HasJsonDataRelations`, `HasJsonDataColumnExtract`, the macros), the repositories' JSON columns, the media references, and the hooks it registers in the package: the 1.x declarations, entities unwrapped into models, the medias module's sync into the usage graph, and the pages models for the rewrite command.

## Medias module

On `laravel-mediatheque` 2 and `laravel-image` 2 ([decisions/0008](decisions/0008-media-references.md)).

- **Models:** `Models\Media` and `Models\MediaFile` extend the mediatheque's. The module sets them in `config('mediatheque.models')` when the site keeps the mediatheque's defaults; a site with its own models sets them there. The package no longer rebinds the mediatheque's model contracts.
- **Entities:** `Entities\Media`, `Image`, `MediaFile` and the metadata entities read the mediatheque's contracts (`original()`, `files()`, `metadata()`), and keep their 1.x contracts and protected properties. `id()` stays the internal id, which `medias://` references and the 1.x JSON shape use; `uid()` gives the public one.
- **Image sizes** are `laravel-image` 2 presets (`config('image.presets')`): one `ImageSize` per preset, with the variant's URL, dimensions and type (`Image::for($media)->preset(...)->variant()`). An image that `laravel-image` can't render (no dimensions, an unknown preset) falls back to its original's URL. `setFilters()` is deprecated, and only understands a preset name, `width`, `height`, `fit`, `format` and `quality`.
- **Creating media:** `createFromFile` goes through `Mediatheque::ingest()`, `updateFromFile` through `replaceOriginal()`. Downloading from a URL, with its host and size limits, stays here until `laravel-mediatheque` imports URLs behind its SSRF guard.
- **JSON references** sync into the mediatheque's `medias_pivot` under the handle `json` ([json-data.md](json-data.md#syncing-media-references)).
- **Reference keys:** the module maps `media` (by uid) and `medias` (by id) to its media model, with `MediaReferences`, and writes the one of `site.medias.reference_key` ([json-data.md](json-data.md#media-references)).
- **JSON:** `Http\Resources\MediaResource` extends the mediatheque's canonical `MediaResource` (its 2.0.0-alpha.10), from an entity or a model. Until 3.0, it gives by default the shape of 1.1 that front ends read (`site.medias.resource` set to `legacy`): the internal `id`, the original's `url`, the entity's `metadata`, an image's `sizes` from the `laravel-image` presets, and `files` as a list with the 1.x handles, the original first. `site.medias.resource` set to `canonical`, or `canonical()`, gives the mediatheque's shape ([#124](https://github.com/folkloreinc/laravel-folklore/issues/124)).

## Pages module

([decisions/0009](decisions/0009-pages-and-blocks.md))

- Blocks are stored in the page's JSON; `blocks_pivot` goes away and `folklore:upgrade` converts existing data.
- The `slug_{locale}` columns stay. Route names (`home`, `page`, `page_with_parent`) are configurable.
- A type registry maps page and block types to entity classes, in `config/site.php` or in code. `HasTypedEntity` reads `$entitiesByType` on the model first, then the registry.

## Auth module

([decisions/0010](decisions/0010-users-and-auth.md)) The `repository` auth driver, the `User` entity with two-factor authentication, and the Fortify callbacks boot only when the module is enabled.

## Panneau 5 plugin

([decisions/0018](decisions/0018-panneau-plugin-php-only.md), which replaces [0011](decisions/0011-panneau-plugin.md)) An adapter for Panneau's `ResourceRepository` on the typed query, `scope()` on the query context, and the `Page`, `Pages`, `Blocks` and `PageSlug` fields. It ships before the end of Panneau 5's betas.

- **PHP only.** The fields extend Panneau 5's built-in types: `Page` and `Pages` are relations to the pages resource, `Blocks` a repeater whose types come from the block type registry, and `PageSlug` Panneau's slug, with the URL that the pages module gives.
- **Slug features in Panneau.** A source per locale, a URL computed by the backend and checking that a slug is free are proposed to Panneau's generic `slug` field.

## Other modules

([decisions/0012](decisions/0012-other-modules.md)) Customer.io, Google (Places on Laravel's HTTP client), PubNub, the view composers and `routeForReactRouter`, the `csv` and `pixel` macros. `daemon:restart` stays in the core. The site manager, organisations, PubSubHubbub and the unused contracts are removed. The global paginator resolver override is removed.

## Compatibility and deprecations

([decisions/0003](decisions/0003-evolve-in-place.md))

- What 2.0 replaces stays on the same class, marked `@deprecated`, and logs a deprecation on Laravel's `deprecations` channel when it is used.
- The 1.1 test suite runs on `v2.x` as the compatibility suite, except what covers Panneau 1.x and the removed modules.
- `folklore:upgrade --check` lists what a site uses that changed, from its code read as text and its database: modules to turn on, removed modules, packages to require, Panneau contracts, the site's own JSON references, unmapped or mismatched JSON keys, block data to convert, the media API, deprecated API ([upgrade.md](upgrade.md#what---check-reports)). `folklore:upgrade` enables the site's modules and converts data.
- Rector rules rename what moved.
- 3.0 removes the deprecated API.

## Testing

- The core's tests run with `illuminate/*` only. Each module's tests run with its dependencies, and check that the module stays off when they are missing.
- Contract tests for the base entity and repository, that sites can run against their own classes.
- `JsonDataCast` keeps the 1.1.8 characterization tests, on SQLite, MySQL and PostgreSQL (`jsonb`).

## Package layout

PSR-4, `Folklore\` → `src/Folklore/`. The files stay where 1.x has them: PSR-0 and PSR-4 resolve every 1.x class to the same path, so the switch moves nothing, and fixes made on `v1.1` keep applying to `v2.x`. New code goes into the same tree, next to the 1.x classes it evolves.

```text
src/Folklore/
├── Contracts/            core contracts
├── Entities/             1.x entities, the typed base entity
├── Repositories/         Entities base class, typed query, context
├── Eloquent/             JsonDataCast (subclass with the macros), the 1.x declarations, MediaReferences, the aliases of the classes moved to laravel-json-data (decisions/0021)
├── Medias/               medias module (proposed)
├── Pages/                pages module (proposed)
├── Auth/                 auth module (proposed)
├── Panneau/              Panneau 5 plugin
├── Services/             Customer.io, Google
├── Support/              helpers, concerns
└── ServiceProvider.php
```

The module namespaces are named with the contracts. 1.x classes that move into a module keep a class alias at their 1.x name until 3.0.

## Dependencies

| Package | 2.0.0 | Alphas (latest: 2.0.0-alpha.4) |
|---|---|---|
| `illuminate/console`, `contracts`, `database`, `pagination`, `support` `^12.69\|^13.30` | Required | Required |
| `folklore/laravel-json-data` ([decisions/0021](decisions/0021-json-data-package.md)) | `^1.0`, required | `^1.0.0-alpha.2`, required since 2.0.0-alpha.4. A site requires it too (`^1.0@alpha`) until it has a stable release |
| `folklore/laravel-mediatheque`, `folklore/laravel-image` | `^2.0`, suggested (medias module) | Suggested: `laravel-mediatheque ^2.0.0-alpha.10`, `laravel-image ^2.0.0-alpha.1`. `laravel-mediatheque` below 2.0.0-alpha.10 conflicts, for its canonical `MediaResource` (below alpha 7 since alpha 2) |
| `folklore/laravel-locale ^8.1`, `cviebrock/eloquent-sluggable` | Suggested (pages module) | Same |
| `laravel/fortify` | Suggested (auth module) | Same |
| `folklore/laravel-panneau` | `^5.0`, suggested (Panneau plugin) | Not yet: the Panneau 1.x fields are removed, the plugin comes with the slice "Panneau 5 adapter and media entities" |
| `guzzlehttp/guzzle`, `parsecsv/php-parsecsv`, `pubnub/pubnub` | Suggested (services) | Same |
| `google/apiclient` | Removed | Suggested, for Places until it moves to Laravel's HTTP client |

## Supported versions

PHP 8.3+ and Laravel 12+ ([decisions/0001](decisions/0001-supported-versions.md)).
