# Changelog

All notable changes to `folklore/laravel-folklore` 2.x are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

2.0 is in development on the `v2.x` branch ([#56](https://github.com/folkloreinc/laravel-folklore/issues/56), [roadmap](docs/roadmap.md)). It is released in slices, tagged `v2.0.0-alpha.N`, then `beta.N` and `rc.N`. Until the release candidate, an alpha can break the previous one: each alpha lists its breaking changes since the previous one under **Breaking changes**.

1.x is released from the [`v1.1`](https://github.com/folkloreinc/laravel-folklore/tree/v1.1) branch; its changes are in its [GitHub releases](https://github.com/folkloreinc/laravel-folklore/releases).

### Breaking changes

- Requires Laravel 12+ ([decision 0001](docs/decisions/0001-supported-versions.md)).
- The `Entity` contract no longer extends Panneau 1.x's `ResourceItem`, and the `Entities` repository contract no longer extends Panneau 1.x's `Repository` ([decision 0003](docs/decisions/0003-evolve-in-place.md)). Neither do the `Page`, `Block` and `MediaFile` entity contracts. Code that passes an entity or a repository to Panneau 1.x as one of its contracts no longer type-checks.

### Added

- `Entities\ModelEntity`, an optional typed base for new entities, with the model's route key as id.
- `HasModel`, `ToEntity` and the `Entities` repository contract are generic, with defaults.
- The typed query contract, `QueriesEntities` (`query(EntitiesQuery)`, `withGlobalQuery()`), with `EntitiesQuery`, `Sort` and `SortDirection`. Repositories implement it in alpha 1.
- `QueryContextResolver`, the contract of the default query context.
- `JsonData`, the registry of JSON reference keys: `map()`, `enforceMap()`, `keysFor()`, `keyFor()`, `parse()`, `reference()`; `JsonReference`, `JsonReferenceEntry`, and the `ReferenceHandler` contract with `ReferenceContext`. `JsonDataCast` uses them in alpha 1.
