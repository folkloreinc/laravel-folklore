# Changelog

All notable changes to `folklore/laravel-folklore` 2.x are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

2.0 is in development on the `v2.x` branch ([#56](https://github.com/folkloreinc/laravel-folklore/issues/56), [roadmap](docs/roadmap.md)). It is released in slices, tagged `v2.0.0-alpha.N`, then `beta.N` and `rc.N`. Until the release candidate, an alpha can break the previous one: each alpha lists its breaking changes since the previous one under **Breaking changes**.

1.x is released from the [`v1.1`](https://github.com/folkloreinc/laravel-folklore/tree/v1.1) branch; its changes are in its [GitHub releases](https://github.com/folkloreinc/laravel-folklore/releases).

### Breaking changes

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

- Module switches in `config/site.php` (`site.modules`), with the package's defaults merged. A module that is on throws `ModuleRequirementMissing` when a package it needs isn't installed.
- `Entities\ModelEntity`, an optional typed base for new entities, whose id is the model's entity key.
- `Contracts\Entities\HasEntityKey`, for models whose entity's public id isn't their key (`getEntityKeyName()`, `getEntityKey()`).
- `HasModel`, `ToEntity` and the `Entities` repository contract are generic, with defaults.
- The typed query contract, `QueriesEntities` (`query(EntitiesQuery)`, `withGlobalQuery()`), with `EntitiesQuery`, `Sort` and `SortDirection`. Repositories implement it in alpha 1.
- `QueryContextResolver`, the contract of the default query context.
- `JsonData`, the registry of JSON reference keys: `map()`, `enforceMap()`, `keysFor()`, `keyFor()`, `parse()`, `reference()`; `JsonReference`, `JsonReferenceEntry`, and the `ReferenceHandler` contract with `ReferenceContext`. `JsonDataCast` uses them in alpha 1.
