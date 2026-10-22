# Current state of 1.x

Audit of `folklore/laravel-folklore` `v1.1` (tag `v1.1.8`), written to plan 2.0. Paths are relative to `src/Folklore/` unless they start with `src/` or `tests/`.

## Summary

1.x is the shared foundation of the Folklore Laravel sites: entities over Eloquent models, repositories that return them, `JsonDataCast` for references in JSON columns, pages and blocks, medias on `laravel-mediatheque` 1.x, users with the `repository` auth provider, and optional services. The concepts are used by every active site. The package has not kept up:

- the core contracts extend Panneau 1.x's, and the package requires `laravel-mediatheque` 1.x, `laravel-image` 1.x and `laravel-panneau` 1.x, so a site can't move to their new majors without leaving 1.x;
- optional modules are required dependencies and most of them boot in every site;
- the repository extension points are untyped, and the global query context is an instance property that some paths ignore;
- `JsonDataCast` resolves references one relation load per model, re-decodes on every access, ties stored references to relation method names, and customizes through string-keyed static macros.

2.0 keeps the concepts and the names, and splits the package into a core and opt-in modules (see [architecture.md](architecture.md) and [decisions/](decisions/)).

## Size

| Directory | PHP files | Lines |
|---|---|---|
| `Contracts/` | 64 | 936 |
| `Services/` (Customer.io, Google, PubSubHubbub) | 25 | 2,508 |
| `Entities/` | 19 | 1,368 |
| `Repositories/` | 6 | 1,327 |
| `Support/` | 15 | 1,205 |
| `Console/` | 11 | 986 |
| `Eloquent/` (`JsonDataCast`) | 1 | 443 |
| `ServiceProvider.php` | 1 | 451 |
| `Models/` | 9 | 398 |
| `Panneau/` | 7 | 394 |
| `Http/` | 10 | 359 |
| Other (`Notifications/`, `Composers/`, `Broadcasters/`, `Site/`, `Routing/`) | 12 | 913 |

About 180 PHP files and 11,300 lines, with 8 migrations (users' role, pages and blocks, organisations) and 8 stubs for the `make:*` generators. Tests: 188 test methods in 34 files (Testbench, SQLite in memory, MySQL in CI), 29 of them for `JsonDataCast`. Larastan runs at level 5 with a baseline of 133 entries, most of them calls through `__call` in `Http/Resources`. Autoloading is PSR-0, with three global helpers in `Support/helpers.php`.

## Dependencies

All required, whether a site uses the feature or not:

| Package | Used by |
|---|---|
| `folklore/laravel-panneau ~1.3.0\|~1.4.0` | The core contracts (`Entity extends ResourceItem`, `Entities extends Repository`), `Panneau/` fields and resources |
| `folklore/laravel-mediatheque ^1.1` | `Models\Media`, `MediaFile`, `HasMedias` on `Page` and `Block`, `Repositories\Medias`, model bindings in the service provider |
| `folklore/laravel-image ^1.1` | `Entities\Image`, `ImageSize` |
| `folklore/laravel-locale ^8.1` | `route_with_locale()` in the `Page` entity, `locale.locales` in the pages migration, composers, `PageSlug`, sluggable fallbacks |
| `laravel/fortify` | The auth callbacks, `Entities\User`, `Models\User` |
| `cviebrock/eloquent-sluggable` | `Models\Page` |
| `google/apiclient` | Google Places only |
| `parsecsv/php-parsecsv` | Google Drive's CSV export only |
| `guzzlehttp/guzzle` | Media download from a URL, Places, `MakesRequests` |

`pubnub/pubnub` is suggested for the PubNub broadcaster.

## Public API

Sites use more than the contracts. Everything below is public API in practice.

### Contracts

- `Contracts\Entities\Entity` (`id(): string`) extends Panneau 1.x's `ResourceItem`. `HasModel` (`getModel(): Model`) and `ToEntity` (`toEntity(): ?Entity`) connect entities and models.
- `Contracts\Repositories\Entities` extends Panneau 1.x's `Repository`: `findById(string $id): ?Entity`, `get(array $query = [], ?int $page = null, ?int $count = 10)`, `count()`, `has()`, `pluck()`, `value()`, `create($data): Entity`, `update(string $id, $data): ?Entity`, `destroy(string $id): bool`, `setGlobalQuery(array $query)`.
- Repository contracts for `Users` (also a Laravel `UserProvider`), `Pages`, `Blocks`, `Medias` and `Organisations`, and 27 entity contracts (pages, blocks, medias and their metadata, users, organisations, `Contact`, `Person`, `Contactable`).
- `Contracts\Eloquent\HasJsonDataRelations` and `HasJsonDataColumnExtract`, implemented by models.

### Repositories

`Repositories\Entities` is the base class of about 130 repositories in active sites. Its protected API is what they override; only `newModel(): Model` is typed.

| Method | Role |
|---|---|
| `newModel()`, `newQuery()` | The model and its base query (`Pages` adds `with('blocks')`) |
| `getDefaultParams()`, `getQueryParams()`, `buildQueryFromParams($query, $params)` | Params: defaults, then the global query, then the call's |
| `orderQuery()`, `getOrderableColumns()` | `order` as a column, a pair or a list of pairs |
| `getQueryFromIdentifier()` | `identifier`: numeric to `id`, otherwise `handle` |
| `getFromQuery($query, $page, $count, $params)` | Pagination: all rows, `paginate()`, or `OffsetPaginator` with `offset_paginator` |
| `saveData($model, $data)` | `fillModel()`, `fillModelJsonAttributes()`, `save()`, `syncRelations()` |
| `fillModelJsonAttributes($model, $data)` | Merges the data that isn't fillable into one JSON column (`$jsonAttributeName`, `data`) |
| `syncRelations($model, $data)` | `JsonDataCast::syncRelations()`, then `refresh()` |

Protected properties: `$globalQuery`, `$jsonAttributeName`, `$jsonAttributeFillable`, `$jsonAttributeExclude`, `$queryColumns` (each adds `{p}`, `or_{p}`, `exclude_{p}` and `or_exclude_{p}` params), `$orderableColumns`, `$identifierHandleColumn`.

The global query is an instance property merged into the params. Repositories are bound with `bind()`, so it only affects the instance it is set on. `findById()`, `update()`, `destroy()`, `Users::findByEmail()` and `valueById()` don't apply it: `findModelById($id, $params)` passes `$params` to `newQuery()`, which takes none.

### Entities

- There is no base entity class: each entity wraps a model and redeclares `$model`, `id()` and `getModel()`. Site entities read protected, untyped properties: `$model`, `$data`, `$metadata`, `$sizes`, `$files`.
- `HasTypedEntity` maps a model's `type` column to an entity class through `$entitiesByType` on the model.
- `to_entity()` and `to_id()` convert between models, entities, arrays and ids.
- `SerializesEntityModel` serializes an entity as its model's class and key, so queued jobs reload it.

### Pages and blocks

- `pages`: `handle`, `type`, `parent_id`, one `slug_{locale}` column per locale configured when the migration ran, `data`, `published`, soft deletes. `blocks`: `handle`, `type`, `data`. `blocks_pivot` links blocks to pages and to other blocks. No foreign keys.
- `Page` declares `parent`, `image` and `blocks.*` in its JSON data. The `Pages` and `Blocks` repositories save nested blocks in a transaction, one query per block.
- The `Page` entity builds URLs with the route names `home`, `page` and `page_with_parent`, through `laravel-locale`.

### Service provider

What boots in every site:

- bindings of the five repository contracts, and of `config('app.repositories')`;
- the mediatheque's `Media` and `File` model contracts, rebound to this package's models;
- the site manager singleton, and the Google bindings;
- the `routeForReactRouter` URL macro, the `pixel` and `csv` response macros;
- a page resolver set on `OffsetPaginator`, which replaces the resolver of **every** paginator: it returns 0 instead of 1 for a missing page;
- the `repository` auth provider and two global Fortify callbacks;
- the `pubnub` broadcast driver;
- the `assets:view`, `users:create` and `daemon:restart` commands.

In the `local` environment only: `LocalMiddleware` as a global middleware, the publishing of the migrations, and the `make:*` generators. Customer.io and PubSubHubbub boot only when configured.

### Optional services

| Module | Notes |
|---|---|
| Customer.io | Client, mail transport, notification channel; boots when `services.customerio` is set |
| Google | Maps (HTTP client), Places (`google/apiclient` and Guzzle), Drive (scraping and `parsecsv`) |
| PubNub | Broadcaster; `pubnub/pubnub` is suggested |
| PubSubHubbub | Client and commands, with stale imports from another application |
| Organisations | Models, entities, repository, migrations, and a `roles` table with no model |
| Site manager | `site.sites` and `site.default` |
| Panneau 1.x | `Page`, `Block`, `Blocks`, `PageSlug` fields; `Medias` resource |

## `JsonDataCast`

The widest-used part of the package after repositories and entities: the contests platform has 15 models that cast a column with it, and 9 of them declare about 90 paths to 7 relations.

### Declaring

A model implements `HasJsonDataRelations::getJsonDataRelations($key, $value, $attributes)` and returns paths mapped to a relation name, or to options: `relation` (a string or a callable), `lazy`, `skip`, `get`, `set`, `before_set`, `sync` (a callable, or `false`), `delete`. Paths use `*` for one segment. `**` matches any depth in `Support\Data`, but is neither tested nor documented.

### Reading

`get()` decodes the column, then, for each declared path, parses `relation://id` and calls `$model->{relation}->find($id)`: the relation is loaded on the model (once per model), and only rows attached to it resolve. A `BelongsTo` returns the related model whatever the id. A `lazy` relation resolves only if it is already loaded, else `null`. Nothing is memoized: every access decodes and resolves again.

The part before `://` is the name of the relation method. A URL or a reference of another relation stored at a reference path is taken for a reference, and renaming a relation breaks stored data.

### Writing

`set()` turns each value at a declared path into `{relation}://{to_id($value)}`. Customizations come from the path's callables or from string-keyed static macros: `JsonDataCast::macro('{relation}:before_set')`, or per model, `'{Model class}:{relation}:set'`. Several sites copied the same trait to accept an uploaded file or a URL in a media field.

`HasJsonDataColumnExtract` copies values of the JSON into columns on write.

### Syncing

`JsonDataCast::syncRelations($model)` runs after the repository saves the model. For each relation, it collects the referenced ids and syncs them by the kind of relation: `sync()` for many-to-many, `associate()` for `BelongsTo`, and attaching the rows without a parent for `HasMany` and `MorphMany`. The repository then calls `refresh()`.

### Fixed in 1.1.8

Characterization tests were added on SQLite and MySQL, with cases from the contests platform ([#60](https://github.com/folkloreinc/laravel-folklore/pull/60)), and four bugs fixed ([#59](https://github.com/folkloreinc/laravel-folklore/issues/59)):

- the `BelongsTo` sync was lost by the `refresh()` after save ([#61](https://github.com/folkloreinc/laravel-folklore/pull/61));
- a reference read raw and written back gained a second prefix ([#62](https://github.com/folkloreinc/laravel-folklore/pull/62));
- a partial update rewrote unresolved references as `null` ([#63](https://github.com/folkloreinc/laravel-folklore/pull/63));
- syncing a `HasMany` moved rows referenced from another parent to this one ([#64](https://github.com/folkloreinc/laravel-folklore/pull/64)).

Not fixed in 1.x: a string at a reference path that isn't an id of the relation (a URL, a prize stored by handle) is still taken for an id. Reference keys fix it in 2.0 ([decisions/0005](decisions/0005-json-reference-keys.md)).

## Known limits

By impact on 2.0:

1. **Coupling to 1.x packages.** The core contracts extend Panneau 1.x's, and the package requires the 1.x line of the mediatheque, `laravel-image` and Panneau.
2. **Boot side effects.** Macros, Fortify callbacks, the broadcast driver, the mediatheque model rebinding, the site manager and the global paginator resolver apply to every site.
3. **Typing.** No `strict_types`. 63% of methods have a return type, none of the repository extension points except `newModel()`, and 19 properties are typed. The 1.x signatures can't get native types without breaking the sites that override them.
4. **Global query.** Not applied by `findById()`, `update()`, `destroy()`; set on a shared instance, it would leak between jobs.
5. **`JsonDataCast`.** One relation load per model, no memoization, references tied to relation names, static macros (see above).
6. **N+1 queries.** `Page::parent()` and `children()` on entities, nested block saves, organisation members.
7. **Schema.** Locale columns fixed at migration time, no foreign keys, a `roles` table with no model, migrations publishable only in `local`.
8. **Dead code.** `SyncRelations` and `QueriesRelations` are unused in the package, the PubSubHubbub manager imports classes from another application, and the `array_is_list` helper is a polyfill for PHP < 8.1.
