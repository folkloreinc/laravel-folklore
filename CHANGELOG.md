# Changelog

All notable changes to `folklore/laravel-folklore` 2.x are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

2.0 is in development on the `v2.x` branch ([#56](https://github.com/folkloreinc/laravel-folklore/issues/56), [roadmap](docs/roadmap.md)). It is released in slices, tagged `v2.0.0-alpha.N`, then `beta.N` and `rc.N`. Until the release candidate, an alpha can break the previous one: each alpha lists its breaking changes since the previous one under **Breaking changes**.

1.x is released from the [`v1.1`](https://github.com/folkloreinc/laravel-folklore/tree/v1.1) branch; its changes are in its [GitHub releases](https://github.com/folkloreinc/laravel-folklore/releases).

## [Unreleased]

## [2.0.0-alpha.5] - 2026-10-29

The upgrade path from 1.1: `MediaResource` on laravel-mediatheque's canonical resource with the `legacy` shape of 1.1 as the default (#124), `folklore:upgrade` with `--check`, and the Rector rules (#123). Requires `laravel-mediatheque` 2.0.0-alpha.10 or later, and `folklore/laravel-json-data` `^1.0.0-alpha.4`: require `folklore/laravel-folklore:^2.0@alpha` with `folklore/laravel-json-data:^1.0@alpha`. See [docs/upgrade.md](docs/upgrade.md).

### Breaking changes

- In a subclass of `MediaResource`, `$this->resource` is the mediatheque's media model, not the entity: `entity()` gives the entity. A subclass that overrides `toArray()` declares it `toArray(Request $request): array`, as the mediatheque's does.
- The medias module needs `laravel-mediatheque` 2.0.0-alpha.10 or later, which has the canonical `MediaResource`: older pre-releases conflict.

### Added

- `Folklore\Http\Resources\MediaResource` extends laravel-mediatheque's canonical `MediaResource`. `site.medias.resource` set to `canonical`, or `canonical()` on the resource (`legacy(false)` on a `MediasCollection`), gives the mediatheque's shape: `id` is the uid, `url` the file the media plays, `files` an object by handle, with `status`, `width`, `height`, `duration` and the rest of its fields ([#124](https://github.com/folkloreinc/laravel-folklore/issues/124)).
- `site.medias.resource`, `legacy` by default until 3.0: the shape of 1.1 that front ends written for it read, which `MediaResource` keeps field for field, for each type of media: the internal `id` as a string, `type`, `name`, the original's `url`, `thumbnail_url`, the entity's `metadata` (`pagesCount` for a document), an image's `sizes` from the `laravel-image` presets, and `files` as a list of `id`, `handle` (`thumbnails.0`), `name`, `url`, `mime` and `size`, the original first. `withoutFiles()`, `withoutMetadata()`, `withFilesAsMap()` and `withoutImageSizes()` keep working on it; the first two also apply to the canonical shape ([#124](https://github.com/folkloreinc/laravel-folklore/issues/124)).
- `MediaResource` takes an entity of the medias module or a mediatheque media model, and reads the model's entity for the legacy shape.
- `folklore:upgrade --check` reports what a 1.1 site uses that changes in 2.0, without writing anything, and exits with an error while there is something to do: modules to turn on (including the macros, the Customer.io client and the `repository` auth provider that 1.1 booted for every site), removed modules, packages that 1.1 required, Panneau's contracts used through Folklore's, models that replace `JsonDataCast` with a plain `json` cast and the code that writes their references, the paths and keys of the references stored in JSON columns with the keys that 2.0 doesn't map or whose identifiers don't match (`media://12` by id), `blocks_pivot`, the media API with `MediaResource`'s shape and subclasses, and the deprecated API. The models are loaded in a child process first, so a class that is a fatal error in 2.0 is reported rather than stopping the check. `--path`, `--model`, `--limit` and `--json` adjust it ([upgrade.md](docs/upgrade.md#what---check-reports), [#123](https://github.com/folkloreinc/laravel-folklore/issues/123)).
- `folklore:upgrade` upgrades a 1.1 site: it turns on the modules the site uses in its `config/site.php` (a module whose packages are missing stays off), sets `medias.reference_key` to `medias` when `medias://` references are stored, and moves the rows of `medias_pivot` that 1.x wrote without a handle for the media of JSON data under the `json` handle: for the models whose JSON data syncs a `medias` relation, it syncs the media a row references, then removes their rows without a handle. It edits the site's config file in place, keeping its comments, and leaves the values that aren't literals (`env(...)`) to the site. `--dry-run` says what it would change; `blocks_pivot` is listed as left to do, until the pages module of 2.0 converts it ([upgrade.md](docs/upgrade.md#what-folkloreupgrade-does), [#123](https://github.com/folkloreinc/laravel-folklore/issues/123)).
- Rector rules for the upgrade from 1.1, `Folklore\Rector\FolkloreSetList::UPGRADE_2_0`: `Folklore\Mediatheque\Support\Traits\HasMedias` → `Folklore\Mediatheque\Concerns\HasMedias`, Panneau's `ResourceItem` and `Repository` → Folklore's `Entity` and `Entities` where they type a value, the 1.x names of the JSON data classes → `Folklore\JsonData\`, `$jsonAttributeName` → `$jsonAttributes`, `new Medias($typeFactory)` → `new Medias()`, and in the subclasses of `MediaResource` a typed `toArray()` and the entity through `entity()` ([upgrade.md](docs/upgrade.md#the-rector-rules)). The site requires `rector/rector` itself.
- The panneau module (`site.modules.panneau`), the first part of the Panneau 5 plugin: a Panneau 5 resource returns a repository contract from `repository()`, and the module adapts every repository with the typed query (`QueriesEntities`) with `Folklore\Panneau\EntitiesRepository`. Lists go through `queryEntities()` (filters as the repository's params, sorts as `order`, `q` as `search`), the resource's `scope()` is the repository's query context (`withGlobalQuery()`), public ids are the entities' `id()`, and the version is a hash of the model's attributes. It needs `folklore/laravel-panneau` 5.0.0-alpha.4 or later, suggested; its older pre-releases conflict ([architecture.md](docs/architecture.md#repository-adapter), [#121](https://github.com/folkloreinc/laravel-folklore/issues/121)).
- `Contracts\Repositories\ChecksSlugs` and its `Repositories\Concerns\ChecksSlugs` trait: whether a slug is taken, in `{field}` or `{field}_{locale}`, within the repository's query context. The Panneau adapter answers the checks of Panneau's unique slugs with it; a repository opts in with the trait.
- `Testing\PanneauRepositoryContractTests`, to check a site's repository through the Panneau adapter.
- `MediaUsagesSync::synced()`, set with `JsonData::syncUsing()`, gives the media synced under the `json` handle, so that laravel-json-data's `json-data:sync-references` compares a model's media with its JSON instead of syncing them on every run, and reports them in its `--dry-run` ([laravel-json-data#5](https://github.com/folkloreinc/laravel-json-data/issues/5)).

### Changed

- Requires `folklore/laravel-json-data` `^1.0.0-alpha.4`, for the second callback of `JsonData::syncUsing()` and `json-data:sync-references`, and for reading JSON columns faster: the stored value is flattened once instead of once per pattern ([laravel-json-data#20](https://github.com/folkloreinc/laravel-json-data/pull/20)).

### Fixed

- The files of a media entity (`Entities\Media::files()`) list the original first, as 1.x did, then the others by handle, rather than all of them by handle; without an original, `url()` is the main rendition's rather than the first file's by handle.

## [2.0.0-alpha.4] - 2026-10-23

JSON data comes from [`folklore/laravel-json-data`](https://github.com/folkloreinc/laravel-json-data), which the core requires (`^1.0.0-alpha.2`, from [v1.0.0-alpha.2](https://github.com/folkloreinc/laravel-json-data/releases/tag/v1.0.0-alpha.2); [decision 0021](docs/decisions/0021-json-data-package.md), [#108](https://github.com/folkloreinc/laravel-folklore/issues/108)). Sites change nothing: the 1.x names, declarations and macros keep working on top of it. Its alpha 2 brings the schema of JSON columns ([decision 0020](docs/decisions/0020-json-schema.md), [#111](https://github.com/folkloreinc/laravel-folklore/issues/111)) ([roadmap](docs/roadmap.md#alpha-4-json-data-in-its-own-package)).

### Breaking changes

- The rewrite command is `json-data:rewrite-references`, laravel-json-data's name for it since its alpha 2, instead of `folklore:json-references:rewrite` ([laravel-json-data's decision 0022](https://github.com/folkloreinc/laravel-json-data/blob/v1.x/docs/decisions/0022-command-names.md)). Scripts and deploy steps that call the old name move to the new one.

### Added

- `Json::media()`, with the medias module: a node of JSON data schemas for a media reference, with the key the module writes (`site.medias.reference_key`), synced to `medias` under the `json` handle.
- `Folklore\Eloquent\SiteLocaleResolver`, bound as laravel-json-data's `LocaleResolver`: the locales of translatable schema nodes are `site.locales` (new, `null` by default), else laravel-locale's `locale.locales`, else laravel-json-data's `json-data.locales`.
- The base repository fills a model that has a schema with `JsonData::fill()`, from a flat input, instead of `$jsonAttributeFillable` and `$jsonAttributeExclude`, which keep working for the models without one ([#104](https://github.com/folkloreinc/laravel-folklore/issues/104)). Under `additional(false)`, a key that is neither fillable nor in the schema is refused with `InvalidJsonData`, and nothing is saved.

### Changed

- `JsonData`, `JsonReference`, `JsonReferenceEntry`, `ReferenceContext`, `ReferenceOperation`, `ModelReferences`, `InvalidJsonReference`, `ReferenceNotAllowed`, `ReferenceHandler` and `JsonReferencesRewriteCommand` are laravel-json-data's, under `Folklore\JsonData\`. Their 1.x names (`Folklore\Eloquent\…`, `Folklore\Contracts\Eloquent\ReferenceHandler`, `Folklore\Console\JsonReferencesRewriteCommand`) are aliases, set when the autoloader starts, so `instanceof` and `catch` match under both.
- `Folklore\Eloquent\JsonDataCast` is a subclass of laravel-json-data's cast that keeps the macros.
- `HasJsonDataRelations`, `HasJsonDataColumnExtract` and the macros reach the cast through `Folklore\Eloquent\LegacyJsonDataDeclarations`, on `JsonData::declareUsing()`. The service provider also plugs in entities (`JsonData::unwrapUsing()`), the sync of the media under the `json` handle (`Folklore\Eloquent\MediaUsagesSync`, on `JsonData::syncUsing()`), and the pages models of the rewrite command.
- The rewrite command is registered by laravel-json-data's service provider, which this package registers.
- After a sync, the `medias` relation is unset only when the media were synced, not after the sync of any relation of a model that uses `HasMedias`.
- The [roadmap](docs/roadmap.md) gets the extraction as alpha 4: the Panneau adapter and the rest of the medias module move to alpha 5, the pages module and the Panneau page fields to alpha 6.

### Deprecated

- The 1.x names of the classes that moved, `Folklore\Eloquent\JsonDataCast` and its macros: use the `Folklore\JsonData\` names and handlers. Removed in 3.0.

## [2.0.0-alpha.3] - 2026-10-23

What the pilot needs to move its JSON references onto `JsonDataCast` ([roadmap](docs/roadmap.md#alpha-3-json-references-on-the-cast), [#89](https://github.com/folkloreinc/laravel-folklore/issues/89)).

### Breaking changes

- Under `JsonData::enforceMap()`, `MediaReferences` takes only media models and entities, `media://` references and uids by default (`MediaReferences::STRICT`): a URL, a file or an internal id throws `InvalidJsonReference`. Set `site.medias.references.accept` to `MediaReferences::FORMS` to keep every form. Without `enforceMap()`, every form is still taken ([#85](https://github.com/folkloreinc/laravel-folklore/issues/85)).
- `JsonDataCast` syncs the relations of a model's references when the model is saved with events, however it is saved, not only through the base repository. Only the relations declared in the JSON attributes that changed are synced. The base repository's `syncRelations()` no longer syncs, and no longer calls `refresh()` on the model: the synced relations are unset instead ([#97](https://github.com/folkloreinc/laravel-folklore/issues/97)).

### Added

- `site.medias.references.accept` lists the forms that `MediaReferences` takes (`model`, `reference`, `uid`, `legacy_id`, `file`, `url`); a value of another form throws `InvalidJsonReference` under `JsonData::enforceMap()`, and is written `null` otherwise, without being downloaded or ingested. `site.medias.references.other_key` set to `false` maps only the key of `site.medias.reference_key` ([#85](https://github.com/folkloreinc/laravel-folklore/issues/85)).
- `JsonData::stored($model, $attribute)`: the decoded value of a JSON attribute with its references as stored, for APIs and front ends. A value that isn't saved yet reads as the reference stored before ([#88](https://github.com/folkloreinc/laravel-folklore/issues/88)).

### Fixed

- `MediaReferences` resolves media through the media model's query instead of the `Mediatheque` facade, so that references to media of the database resolve under `Mediatheque::fake()`, which only knows the media it ingested ([#98](https://github.com/folkloreinc/laravel-folklore/issues/98)).
- `JsonDataCast` registered its `saving` listener once per event dispatcher by object id: a new dispatcher that reused the id of one that was gone, as between tests, got no listener, and pending values weren't prepared.

## [2.0.0-alpha.2] - 2026-10-23

Checks for the pilot's database, and docs: no new APIs ([roadmap](docs/roadmap.md#alpha-2-checks-for-the-pilots-database), [#89](https://github.com/folkloreinc/laravel-folklore/issues/89)). The `Entities` contract stays as it is ([#83](https://github.com/folkloreinc/laravel-folklore/issues/83)).

### Breaking changes

- `folklore/laravel-mediatheque` below 2.0.0-alpha.7 conflicts with the package: the medias and pages modules need its `HasMedias` (alpha.6) and its migrations that run on MySQL (alpha.7), and `suggest` didn't enforce a version. A project on an older mediatheque updates it with this package.

### Added

- CI runs the test suite on PostgreSQL 17, where the JSON fixtures' columns are `jsonb` ([#87](https://github.com/folkloreinc/laravel-folklore/issues/87)).

### Changed

- The [roadmap](docs/roadmap.md) gets two alphas before the Panneau adapter, for what the pilot needs on alpha 1: alpha 2 adds checks for its database, alpha 3 moves its JSON references onto the cast. The Panneau adapter and the rest of the medias module move to alpha 4, the pages module and the Panneau page fields to alpha 5 ([#89](https://github.com/folkloreinc/laravel-folklore/issues/89)).

### Fixed

- The base repository passes its `TEntity` to the `Entities` and `QueriesEntities` contracts. A repository contract typed on its entity (`@extends Entities<UserEntity>`) and the base class (`@extends Entities<User, UserEntity>`) no longer conflict in static analysis, and `findById()`, `update()`, `create()` and `queryEntities()` return `TEntity`.
- `create()` throws a `LogicException` when the model it created gives no entity, instead of a `TypeError` on its return type.
- On PostgreSQL, whose `LIKE` is case-sensitive, `findBySlug()` of the organisations repository, `findByEmail()` of the users repository (`whereLikeLiteral()`) and the `search` param of the medias repository are case-insensitive, as on SQLite and MySQL ([#87](https://github.com/folkloreinc/laravel-folklore/issues/87)).

## [2.0.0-alpha.1] - 2026-10-23

The core and `JsonDataCast`, and the medias module on `laravel-mediatheque` 2 and `laravel-image` 2 ([roadmap](docs/roadmap.md#alpha-1-the-core-and-jsondatacast)). The breaking changes are those since 1.1.8; the [upgrade guide](docs/upgrade.md) lists what a site changes.

### Breaking changes

- `findById()`, `update()`, `destroy()` and `valueById()` apply the repository's query context (the global query, `withGlobalQuery()`, the default resolver), without its default params ([decision 0017](docs/decisions/0017-finds-apply-the-query-context.md)). In 1.x they ignored it.
- The typed query method of `QueriesEntities` is `queryEntities()`, not `query()`, which 1.x repositories may define.
- With the medias module on, a media reference resolves by its key whether or not the media is attached to the owner, as every mapped key does; in 1.x, only attached media resolved. `MediaReferences::allowUsing()` scopes it.
- A string at a reference path that isn't a reference of its relation (a URL, `blocks://1` under a `medias` path) is no longer taken for an id: it is written `null` when the handler can't prepare it, read as is, and not synced. 1.x wrote `medias://https://…`, and synced a reference to the relation named in it.
- Values written to a reference path are turned into references when the model is saved, not when they are assigned: until then, the raw attribute keeps the reference stored before. A model saved without events (`saveQuietly()`) keeps them pending.
- Requires Laravel 12+ ([decision 0001](docs/decisions/0001-supported-versions.md)).
- Requires only `illuminate/console`, `contracts`, `database`, `pagination` and `support`. `folklore/laravel-mediatheque`, `folklore/laravel-image`, `folklore/laravel-locale`, `cviebrock/eloquent-sluggable`, `laravel/fortify`, `guzzlehttp/guzzle`, `google/apiclient` and `parsecsv/php-parsecsv` are suggested: a site requires those its modules need ([decision 0002](docs/decisions/0002-core-and-modules.md)).
- The medias and pages modules are on `laravel-mediatheque` 2 and `laravel-image` 2 (`^2.0.0-alpha.7` and `^2.0.0-alpha.1`, suggested), instead of their 1.x:
  - `Models\Media` and `Models\MediaFile` extend the mediatheque 2 models. The medias module sets them in `config('mediatheque.models')` unless the site sets its own; the package no longer rebinds the mediatheque's model contracts.
  - The media entities read the mediatheque's contracts. Their 1.x contracts don't change, and `id()` stays the internal id; `Entities\Media::uid()` gives the public one. The metadata entities no longer have `getMetadatas()`.
  - Image sizes are `laravel-image` 2 presets (`config('image.presets')`), not `config('image.sizes')`. `ImageSize` takes a preset name. `Image::setFilters()` is deprecated and only understands a preset name, `width`, `height`, `fit`, `format` and `quality`.
  - `Repositories\Medias::createFromFile()` ingests with `Mediatheque::ingest()`, and `updateFromFile()` replaces the original with `Mediatheque::replaceOriginal()`. Its constructor takes no type factory.
  - `Page`, `Block` and the models of sites use the mediatheque 2's `Folklore\Mediatheque\Concerns\HasMedias`.
  - `JsonDataCast` syncs the media referenced in JSON data with `syncMedias()`, under the `json` handle (`JsonDataCast::MEDIAS_HANDLE`): media attached otherwise are no longer detached, and a reference that isn't a media id is no longer written to the pivot.
- The Panneau 1.x fields and resources (`Panneau\Fields\*`, `Panneau\Resources\*`) are removed, with `folklore/laravel-panneau` 1.x. A Panneau 5 plugin replaces them: its repository adapter in alpha 2, its page fields with the pages module in alpha 3 ([decision 0018](docs/decisions/0018-panneau-plugin-php-only.md)).
- Everything the service provider booted is behind a module, off by default: the medias, pages, users and organisations repositories, the mediatheque model bindings, the `repository` auth provider and the Fortify callbacks, the site manager, the `routeForReactRouter` macro, the `pixel` and `csv` responses and the offset page resolver, `assets:view` and `LocalMiddleware`, `users:create`, Customer.io, Google, the `pubnub` driver and PubSubHubbub. Turn on the modules a site uses in `config/site.php` (`modules`).
- The `Entity` contract no longer extends Panneau 1.x's `ResourceItem`, and the `Entities` repository contract no longer extends Panneau 1.x's `Repository` ([decision 0003](docs/decisions/0003-evolve-in-place.md)). Neither do the `Page`, `Block` and `MediaFile` entity contracts. Code that passes an entity or a repository to Panneau 1.x as one of its contracts no longer type-checks.

### Added

- Repositories run `saveData()` in a database transaction, in `create()` and `update()`: a save that fails after the model's `saving` event rolls back the media its JSON references ingested.
- `make:entity` generates an entity on `ModelEntity` (`@extends ModelEntity<Model>`), and repositories and their contracts with their types (`@extends Entities<Model, Entity>`).
- The base repository implements `QueriesEntities`: `queryEntities(EntitiesQuery)` gives a page of entities with the repository's params as filters, `search`, sorts, and ids and exclusions by entity key; `withGlobalQuery()` returns a scoped copy.
- A `QueryContextResolver` bound in the container scopes every repository (a tenant from the request).
- Repositories find by the model's entity key (`HasEntityKey`), or its key.
- `Folklore\Testing\EntitiesRepositoryContractTests`, a trait that checks that a repository finds the entities it creates by their `id()`.
- The base repository has `TModel` and `TEntity` templates, for `@extends Entities<Model, Entity>`.
- `JsonData::load($models, $attributes)` resolves the references of a collection together: one call to each key's handler, and the relations of unmapped keys eager-loaded. Repositories do it for their results with `withJsonReferences()`, which returns a copy.
- `JsonData::references($model)` lists the references stored in a model's JSON attributes, with their attribute, path and relation, whether they resolve or not.
- `$jsonAttributes` on repositories: the JSON attributes after the first are given as a whole and merged into their stored value.
- `php artisan folklore:json-references:rewrite --from=medias --to=media [--model=…] [--dry-run]` rewrites the references of one key to another, in chunks and without model events.
- Media references: the medias module maps `media` (`media://med_…`, by uid) and `medias` (`medias://12`, by id) to its media model, with `Folklore\Eloquent\MediaReferences`, and writes the key of `site.medias.reference_key` (`media` by default; `medias` for upgraded sites). `MediaReferences` takes media, entities, references, uids, 1.x ids, Panneau 5 values, uploaded files, files and URLs, which it ingests when the owner is saved; `ingestUsing()` and `allowUsing()` plug a tenancy into it.
- `Repositories\Medias::downloadForIngest()`, which downloads a URL with the repository's limits.
- `JsonDataCast` on the registry of keys ([json-data.md](docs/json-data.md)):
  - stored references are `{key}://{identifier}`, where the key is mapped with `JsonData::map()`, or, when it isn't, the name of the relation, as in 1.x;
  - a relation writes its `key` option, else the first key mapped to its related model, else its name; on read, every mapped key of the related model is accepted;
  - values other than references (models, entities, identifiers) are prepared when the model is saved, by the key's handler, which checks that the owner may refer to them (`ReferenceNotAllowed`);
  - the references of a mapped key resolve in one query per key, through its handler (`ModelReferences` by default, or `using`), and are memoized for the raw value;
  - `body.**` matches references at any depth, where only mapped keys are references;
  - `JsonData::enforceMap()` rejects unmapped keys.
- Module switches in `config/site.php` (`site.modules`), with the package's defaults merged. A module that is on throws `ModuleRequirementMissing` when a package it needs isn't installed.
- `Entities\ModelEntity`, an optional typed base for new entities, whose id is the model's entity key.
- `Contracts\Entities\HasEntityKey`, for models whose entity's public id isn't their key (`getEntityKeyName()`, `getEntityKey()`).
- `HasModel`, `ToEntity` and the `Entities` repository contract are generic, with defaults.
- The typed query contract, `QueriesEntities` (`queryEntities(EntitiesQuery)`, `withGlobalQuery()`), with `EntitiesQuery`, `Sort` and `SortDirection`.
- `QueryContextResolver`, the contract of the default query context.
- `JsonData`, the registry of JSON reference keys: `map()`, `enforceMap()`, `keysFor()`, `keyFor()`, `parse()`, `reference()`; `JsonReference`, `JsonReferenceEntry`, and the `ReferenceHandler` contract with `ReferenceContext`. `JsonDataCast` uses them in alpha 1.

### Fixed

- `make:entity` generated an entity and a repository that implemented themselves instead of their contracts, so they didn't load.

[Unreleased]: https://github.com/folkloreinc/laravel-folklore/compare/v2.0.0-alpha.5...v2.x
[2.0.0-alpha.5]: https://github.com/folkloreinc/laravel-folklore/releases/tag/v2.0.0-alpha.5
[2.0.0-alpha.4]: https://github.com/folkloreinc/laravel-folklore/releases/tag/v2.0.0-alpha.4
[2.0.0-alpha.3]: https://github.com/folkloreinc/laravel-folklore/releases/tag/v2.0.0-alpha.3
[2.0.0-alpha.2]: https://github.com/folkloreinc/laravel-folklore/releases/tag/v2.0.0-alpha.2
[2.0.0-alpha.1]: https://github.com/folkloreinc/laravel-folklore/releases/tag/v2.0.0-alpha.1
