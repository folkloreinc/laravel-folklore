# Roadmap of 2.0

2.0 is built on the `v2.x` branch in slices, each released as a tagged pre-release (`v2.0.0-alpha.N`, then beta and RC). The pilots pull each slice from their needs: Niche requires `^2.0@alpha` for the core and `JsonDataCast`; the contests platform, the widest user of `JsonDataCast` and the pilot of Panneau 5's phase 2, is the candidate to upgrade during the beta. 1.x stays maintained on `v1.1` for fixes and security.

Tracking issues: [#1](https://github.com/folkloreinc/laravel-folklore/issues/1) (overall roadmap) and [#56](https://github.com/folkloreinc/laravel-folklore/issues/56) (plan of 2.0). Each item becomes a sub-issue when its work starts.

## Rules

- **Contracts first.** Each public contract is written with its tests before its implementation, and reviewed against its consumers (the pilots, `laravel-mediatheque` 2, Panneau 5) before the alpha that ships it.
- **Each alpha is usable end to end** for what it contains, with docs. Nothing half-done is tagged.
- Until the release candidate, an alpha can break the previous one. Breaking changes between alphas are listed in the CHANGELOG.
- **The 1.1 test suite keeps passing** on `v2.x` from alpha 1 on, except what covers Panneau 1.x and the removed modules ([decisions/0003](decisions/0003-evolve-in-place.md)).
- An alpha that needs another package's pre-release requires it as `^2.0@alpha` (or `^5.0@alpha`), in `require-dev` and `suggest`.
- Releases are tagged from a green CI on `v2.x`.

## Phase 0: kickoff

- [x] `v2.x` branch, created from `v1.1` (`v1.1.8`)
- [x] `docs/`: current state, architecture, `JsonDataCast` spec, decisions, this roadmap, upgrade guide ([#67](https://github.com/folkloreinc/laravel-folklore/pull/67))
- [x] Settle the open points of phase 0: supported versions ([decisions/0001](decisions/0001-supported-versions.md)), deferring `prepare()` ([decisions/0013](decisions/0013-defer-prepare-until-saving.md)), names ([decisions/0014](decisions/0014-names.md))
- [x] Tooling: PSR-4, PHP 8.3+, Laravel 12+, CI matrix (PHP 8.3 to 8.5 × Laravel 12 and 13, SQLite and MySQL), Pint, Larastan at the maximum level for new code (the 1.x baseline stays for 1.x code)
- [x] Release process: changelog, `v2.0.0-alpha.N` tags from a green CI, as `laravel-image` 2 does (see "Releases" in [AGENTS.md](../AGENTS.md))
- [x] Contracts and their tests: `Entity`, `HasModel`, `ToEntity` without Panneau; the `Entities` repository contract and its typed query; the query context resolver; `JsonData`, `JsonReference`, `ReferenceHandler`, `ReferenceContext`
- [x] Review the contracts against the pilot's prototype of the core and against `laravel-mediatheque` 2's references and usage graph ([architecture.md](architecture.md#review-against-the-pilots-prototype))

## Alpha 1: the core and `JsonDataCast`

What both pilots need first: Niche's brand images, and the contests platform before it rewrites its JSON data.

- [x] Composer: the core requires only `illuminate/*`; the Folklore packages, Fortify, Guzzle, `google/apiclient`, `parsecsv` and `pubnub` move to `suggest` ([decisions/0002](decisions/0002-core-and-modules.md)). `google/apiclient` stays in `require-dev` for Places, until it moves to Laravel's HTTP client. A CI job runs the core's tests without the modules' packages
- [x] Modules switched in `config/site.php`, all off by default; a module that is on checks its packages ([architecture.md](architecture.md#configuration-and-modules)); modules that alpha 1 doesn't port yet stay off and don't load their 1.x dependencies
- [x] Contracts without Panneau 1.x; optional typed base entity; typed query next to `get()` (`queryEntities()`); the base class's templates (`@extends Entities<TModel, TEntity>`). Typing the 1.x method bodies well enough to declare `TEntity` returns is left for the beta, with the deprecated API
- [x] Global query context: `withGlobalQuery()`, default resolver ([decisions/0004](decisions/0004-global-query-context.md)); finds apply it ([decisions/0017](decisions/0017-finds-apply-the-query-context.md))
- [x] The base repository finds by the model's entity key (`HasEntityKey`), or its key, in `findById()`, `update()`, `destroy()` and the `ids` of `EntitiesQuery`, with the contract test that `findById($entity->id())` finds the entity ([decisions/0015](decisions/0015-entity-key.md)); `@template TModel, TEntity` on the base class (`Folklore\Testing\EntitiesRepositoryContractTests`)
- [x] `make:entity` and `make:repository` on the 2.0 bases: entities on `ModelEntity`, repositories and their contracts with their types; the generated classes load again (1.x generated an entity and a repository that implemented themselves)
- [x] Repositories save in a database transaction ([decisions/0013](decisions/0013-defer-prepare-until-saving.md))
- [x] `JsonDataCast` ([json-data.md](json-data.md), decisions [0005](decisions/0005-json-reference-keys.md) to [0007](decisions/0007-batch-resolution-and-listing.md)):
  - [x] registry of keys, `map()`, `enforceMap()`, per-relation `key`, fallback to the relation name
  - [x] handlers per key, default handler (`ModelReferences`), `using` per path; 1.x macros and callables kept; values prepared on `saving` ([decisions/0013](decisions/0013-defer-prepare-until-saving.md))
  - [x] one query per key for a model, memoization by raw value
  - [x] batch resolution of a collection (`JsonData::load()`, `withJsonReferences()`)
  - [x] reference listing (`JsonData::references()`), `JsonData::parse()`
  - [x] recursive wildcard `**`
  - [x] repositories with several JSON columns (`$jsonAttributes`)
  - [x] `folklore:json-references:rewrite`
- [x] Medias module on `laravel-mediatheque ^2.0@alpha` (2.0.0-alpha.7: `HasMedias` since alpha.6, migrations that run on MySQL since alpha.7) and `laravel-image ^2.0@alpha`, moved from alpha 2:
  - [x] `Media` and `MediaFile` models on the mediatheque's, set in `config('mediatheque.models')` unless the site sets its own; the package no longer rebinds the mediatheque's model contracts
  - [x] `Entities\Media`, `Image`, `ImageSize`, `MediaFile` and the metadata entities on the mediatheque's contracts; image sizes are `laravel-image` presets (`Image::for($media)->preset(...)->variant()`); protected properties kept
  - [x] `createFromFile` and `updateFromFile` through `Mediatheque::ingest()` and `replaceOriginal()`; the download from a URL, with its limits, stays here
  - [x] the pages models and the JSON media sync on the mediatheque's `HasMedias`, by handle ([json-data.md](json-data.md#syncing))
- [x] Medias module, references: the `media` and `medias` keys and `MediaReferences`, with `site.medias.reference_key` ([decisions/0008](decisions/0008-media-references.md))
- [x] Panneau 1.x removed: its fields and resources, and `folklore/laravel-panneau` 1.x
- [x] The 1.1.8 characterization tests of `JsonDataCast` pass, but three that the spec changes on purpose: a string that isn't a reference of its relation is neither written nor synced as an id, a reference is synced only to the relation of its path, and a media reference resolves before it is attached ([CHANGELOG](../CHANGELOG.md))
- [ ] Niche replaces its prototype with `^2.0@alpha`, once `v2.0.0-alpha.1` is tagged

## Alpha 2: checks for the pilot's database

The pilot replaces its prototype on alpha 1. It runs PostgreSQL with `jsonb` columns, which neither this package nor the mediatheque tested. Alpha 2 adds checks and docs, not new APIs ([#89](https://github.com/folkloreinc/laravel-folklore/issues/89)). The `Entities` contract stays as it is ([#83](https://github.com/folkloreinc/laravel-folklore/issues/83), closed).

- [ ] The test suite on PostgreSQL 17, where the JSON fixtures' columns are `jsonb` ([#87](https://github.com/folkloreinc/laravel-folklore/issues/87))
- [ ] `conflict` with `laravel-mediatheque` below 2.0.0-alpha.7: the medias module needs `HasMedias` (alpha.6) and the migrations that run on MySQL (alpha.7), and `suggest` doesn't enforce a version
- [ ] The base repository passes its `TEntity` to its contracts, so that a repository contract typed on its entity passes static analysis ([#90](https://github.com/folkloreinc/laravel-folklore/pull/90))
- [ ] The `ShortUuid` test that failed at random, on `v2.x` and `v1.1` ([#66](https://github.com/folkloreinc/laravel-folklore/issues/66))
- [ ] Docs: the usage graph and the JSON sync settled, and the limits of the repository contract found in the review of the prototype ([architecture.md](architecture.md#review-against-the-pilots-prototype)); `resolveMany()` taking identifiers settled, and the three 1.1.8 tests that change on purpose ([json-data.md](json-data.md))

## Alpha 3: JSON references on the cast

What the pilot needs to move its JSON references onto `JsonDataCast`.

- [ ] `MediaReferences`: the forms of references it accepts, with a strict mode ([#85](https://github.com/folkloreinc/laravel-folklore/issues/85))
- [ ] `JsonData::stored()`: a JSON column with its references as stored ([#88](https://github.com/folkloreinc/laravel-folklore/issues/88))

## Alpha 4: the rest of the medias module, and the Panneau adapter

- [ ] Panneau 5 plugin, its first part: the `ResourceRepository` adapter on the typed query, `scope()` on the query context, and `ChecksSlugs` (`slugExists()`) for the checks of Panneau's slug fields ([decisions/0018](decisions/0018-panneau-plugin-php-only.md), `laravel-panneau`'s [decision 0011](https://github.com/folkloreinc/laravel-panneau/blob/v5.x/docs/decisions/0011-slug-checks.md), proposed); `laravel-panneau`'s architecture updated for the adapter. Moved from alpha 1, which Niche needs without Panneau, then after the checks and the JSON references that it needs first

Once `laravel-mediatheque` 2 has them:

- [ ] `MediaResource` on the mediatheque's canonical resource, with the `legacy` shape (its alpha 2)
- [ ] The download from a URL moved to the mediatheque, behind its SSRF guard (its alpha 3)

## Alpha 5: pages

- [ ] Pages module: blocks in the page's JSON, type registry, configurable route names ([decisions/0009](decisions/0009-pages-and-blocks.md))
- [ ] The Panneau plugin's page fields: `Page`, `Pages`, `Blocks` and `PageSlug`, in PHP on Panneau 5's built-in types, with the URL of a slug from the pages module ([decisions/0018](decisions/0018-panneau-plugin-php-only.md)); `PageSlug` on Panneau's generic slug field ([folkloreinc/panneau-js#109](https://github.com/folkloreinc/panneau-js/issues/109))

## Later

Not scheduled yet: each item goes into the alpha or beta that needs it.

- [ ] Reference paths with a key and no Eloquent relation ([#84](https://github.com/folkloreinc/laravel-folklore/issues/84)). The pilot moves its provenance references to foreign key columns instead
- [ ] `folklore:json-references:sync`: sync the relations of stored JSON references, for rows written without model events ([#86](https://github.com/folkloreinc/laravel-folklore/issues/86)). Not needed while the pilot starts from empty databases; needed before its first import, and for upgraded sites
- An idea, not planned: rows computed from JSON entries, for list items that repeat the same related model with different attributes (one person with two roles)

## Beta: upgrade path

- [ ] Auth module, opt-in ([decisions/0010](decisions/0010-users-and-auth.md))
- [ ] Other modules opt-in; Places on Laravel's HTTP client; removed modules gone; paginator override removed ([decisions/0012](decisions/0012-other-modules.md))
- [ ] Deprecated API complete, with notices on the `deprecations` channel
- [ ] `folklore:upgrade`, with `--check`: modules, block data, JSON keys, removed modules
- [ ] Rector rules
- [ ] An active site upgrades (the contests platform is the candidate)

The auth and other modules are placed in the beta because the site that upgrades needs them; a pilot that needs one earlier pulls it into an alpha.

## Release candidate

- [ ] Public API frozen; contracts documented
- [ ] README rewritten; upgrade guide complete
- [ ] The upgraded site runs the RC in production

## 2.0.0

- [ ] Every item above done, CI green on the whole matrix
- [ ] `v2.x` becomes the default branch

## After 2.0.0

- Upgrade of the other active 1.1 sites, usually with their move to Panneau 5
- 3.0, later: remove the deprecated API

## Open questions

- How `Page` restricts the pages it offers to some types, as `withTypes()` did in 1.x: a relation filter in Panneau 5, or a pages resource per type (alpha 5).
- Whether a 1.2 accepts `^1.1|^2.0` of the mediatheque and `laravel-image`, so that sites upgrade them before this package (decided at the mediatheque's beta).
