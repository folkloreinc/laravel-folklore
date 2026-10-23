# Changelog

All notable changes to `folklore/laravel-folklore` 2.x are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

2.0 is in development on the `v2.x` branch ([#56](https://github.com/folkloreinc/laravel-folklore/issues/56), [roadmap](docs/roadmap.md)). It is released in slices, tagged `v2.0.0-alpha.N`, then `beta.N` and `rc.N`. Until the release candidate, an alpha can break the previous one: each alpha lists its breaking changes since the previous one under **Breaking changes**.

1.x is released from the [`v1.1`](https://github.com/folkloreinc/laravel-folklore/tree/v1.1) branch; its changes are in its [GitHub releases](https://github.com/folkloreinc/laravel-folklore/releases).

### Breaking changes

- With the medias module on, a media reference resolves by its key whether or not the media is attached to the owner, as every mapped key does; in 1.x, only attached media resolved. `MediaReferences::allowUsing()` scopes it.
- A string at a reference path that isn't a reference of its relation (a URL, `blocks://1` under a `medias` path) is no longer taken for an id: it is written `null` when the handler can't prepare it, read as is, and not synced. 1.x wrote `medias://https://…`, and synced a reference to the relation named in it.
- Values written to a reference path are turned into references when the model is saved, not when they are assigned: until then, the raw attribute keeps the reference stored before. A model saved without events (`saveQuietly()`) keeps them pending.
- Requires Laravel 12+ ([decision 0001](docs/decisions/0001-supported-versions.md)).
- Requires only `illuminate/console`, `contracts`, `database`, `pagination` and `support`. `folklore/laravel-mediatheque`, `folklore/laravel-image`, `folklore/laravel-locale`, `cviebrock/eloquent-sluggable`, `laravel/fortify`, `guzzlehttp/guzzle`, `google/apiclient` and `parsecsv/php-parsecsv` are suggested: a site requires those its modules need ([decision 0002](docs/decisions/0002-core-and-modules.md)).
- The medias and pages modules are on `laravel-mediatheque` 2 and `laravel-image` 2 (`^2.0@alpha`), instead of their 1.x:
  - `Models\Media` and `Models\MediaFile` extend the mediatheque 2 models. The medias module sets them in `config('mediatheque.models')` unless the site sets its own; the package no longer rebinds the mediatheque's model contracts.
  - The media entities read the mediatheque's contracts. Their 1.x contracts don't change, and `id()` stays the internal id; `Entities\Media::uid()` gives the public one. The metadata entities no longer have `getMetadatas()`.
  - Image sizes are `laravel-image` 2 presets (`config('image.presets')`), not `config('image.sizes')`. `ImageSize` takes a preset name. `Image::setFilters()` is deprecated and only understands a preset name, `width`, `height`, `fit`, `format` and `quality`.
  - `Repositories\Medias::createFromFile()` ingests with `Mediatheque::ingest()`, and `updateFromFile()` replaces the original with `Mediatheque::replaceOriginal()`. Its constructor takes no type factory.
  - `Page`, `Block` and the models of sites use the mediatheque 2's `Folklore\Mediatheque\Concerns\HasMedias`.
  - `JsonDataCast` syncs the media referenced in JSON data with `syncMedias()`, under the `json` handle (`JsonDataCast::MEDIAS_HANDLE`): media attached otherwise are no longer detached, and a reference that isn't a media id is no longer written to the pivot.
- The Panneau 1.x fields and resources (`Panneau\Fields\*`, `Panneau\Resources\*`) are removed, with `folklore/laravel-panneau` 1.x. A Panneau 5 plugin replaces them later in alpha 1.
- Everything the service provider booted is behind a module, off by default: the medias, pages, users and organisations repositories, the mediatheque model bindings, the `repository` auth provider and the Fortify callbacks, the site manager, the `routeForReactRouter` macro, the `pixel` and `csv` responses and the offset page resolver, `assets:view` and `LocalMiddleware`, `users:create`, Customer.io, Google, the `pubnub` driver and PubSubHubbub. Turn on the modules a site uses in `config/site.php` (`modules`).
- The `Entity` contract no longer extends Panneau 1.x's `ResourceItem`, and the `Entities` repository contract no longer extends Panneau 1.x's `Repository` ([decision 0003](docs/decisions/0003-evolve-in-place.md)). Neither do the `Page`, `Block` and `MediaFile` entity contracts. Code that passes an entity or a repository to Panneau 1.x as one of its contracts no longer type-checks.

### Added

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
- The typed query contract, `QueriesEntities` (`query(EntitiesQuery)`, `withGlobalQuery()`), with `EntitiesQuery`, `Sort` and `SortDirection`. Repositories implement it in alpha 1.
- `QueryContextResolver`, the contract of the default query context.
- `JsonData`, the registry of JSON reference keys: `map()`, `enforceMap()`, `keysFor()`, `keyFor()`, `parse()`, `reference()`; `JsonReference`, `JsonReferenceEntry`, and the `ReferenceHandler` contract with `ReferenceContext`. `JsonDataCast` uses them in alpha 1.
