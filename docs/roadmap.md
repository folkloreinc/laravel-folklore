# Roadmap of 2.0

2.0 is built on the `v2.x` branch in slices named by theme. Pre-releases (`v2.0.0-alpha.N`, then beta and RC) are tagged when a part is ready, and each item says the release that shipped it; what each release contains is in the [CHANGELOG](../CHANGELOG.md). The pilots pull the slices from their needs: Niche runs the core and the JSON data on the alphas; the contests platform, the widest user of `JsonDataCast` and the pilot of Panneau 5's phase 2, is the candidate to upgrade during the beta. 1.x stays maintained on `v1.1` for fixes and security only: the 1.2 once planned in #1 isn't made, and its items go to 2.0 unless an existing site needs one.

Index: [#113](https://github.com/folkloreinc/laravel-folklore/issues/113). Each item becomes a sub-issue of it when its work starts. 1.x is tracked in [#1](https://github.com/folkloreinc/laravel-folklore/issues/1).

## Rules

- **Contracts first.** Each public contract is written with its tests before its implementation, and reviewed against its consumers (the pilots, `laravel-mediatheque` 2, Panneau 5) before the alpha that ships it.
- **Each pre-release is usable end to end** for what it contains, with docs. Nothing half-done is tagged.
- Until the release candidate, an alpha can break the previous one. Breaking changes between alphas are listed in the CHANGELOG.
- **The 1.1 test suite keeps passing** on `v2.x` from 2.0.0-alpha.1 on, except what covers Panneau 1.x and the removed modules ([decisions/0003](decisions/0003-evolve-in-place.md)).
- A module that needs another package's pre-release suggests it with the lowest version it needs (`^2.0.0-alpha.7`), requires it in `require-dev` for the tests, and declares older pre-releases in `conflict` when it can't run on them. `folklore/laravel-json-data` is required by the core, with the lowest alpha it needs (`^1.0.0-alpha.2`).
- Releases are tagged from a green CI on `v2.x`.

## Kickoff (done)

- [x] `v2.x` branch, created from `v1.1` (`v1.1.8`)
- [x] `docs/`: current state, architecture, `JsonDataCast` spec, decisions, this roadmap, upgrade guide ([#67](https://github.com/folkloreinc/laravel-folklore/pull/67))
- [x] Settle the open points of phase 0: supported versions ([decisions/0001](decisions/0001-supported-versions.md)), deferring `prepare()` ([decisions/0013](decisions/0013-defer-prepare-until-saving.md)), names ([decisions/0014](decisions/0014-names.md))
- [x] Tooling: PSR-4, PHP 8.3+, Laravel 12+, CI matrix (PHP 8.3 to 8.5 × Laravel 12 and 13, SQLite and MySQL), Pint, Larastan at the maximum level for new code (the 1.x baseline stays for 1.x code)
- [x] Release process: changelog, `v2.0.0-alpha.N` tags from a green CI, as `laravel-image` 2 does (see "Releases" in [AGENTS.md](../AGENTS.md))
- [x] Contracts and their tests: `Entity`, `HasModel`, `ToEntity` without Panneau; the `Entities` repository contract and its typed query; the query context resolver; `JsonData`, `JsonReference`, `ReferenceHandler`, `ReferenceContext`
- [x] Review the contracts against the pilot's prototype of the core and against `laravel-mediatheque` 2's references and usage graph ([architecture.md](architecture.md#review-against-the-pilots-prototype))

## The core and `JsonDataCast` (2.0.0-alpha.1)

What both pilots need first: Niche's brand images, and the contests platform before it rewrites its JSON data.

- [x] Composer: the core requires only `illuminate/*`; the Folklore packages, Fortify, Guzzle, `google/apiclient`, `parsecsv` and `pubnub` move to `suggest` ([decisions/0002](decisions/0002-core-and-modules.md)). `google/apiclient` stays in `require-dev` for Places, until it moves to Laravel's HTTP client. A CI job runs the core's tests without the modules' packages
- [x] Modules switched in `config/site.php`, all off by default; a module that is on checks its packages ([architecture.md](architecture.md#configuration-and-modules)); modules that 2.0.0-alpha.1 doesn't port yet stay off and don't load their 1.x dependencies
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
- [x] Medias module on `laravel-mediatheque ^2.0.0-alpha.7` (`HasMedias` since its alpha.6, migrations that run on MySQL since its alpha.7) and `laravel-image ^2.0.0-alpha.1`:
  - [x] `Media` and `MediaFile` models on the mediatheque's, set in `config('mediatheque.models')` unless the site sets its own; the package no longer rebinds the mediatheque's model contracts
  - [x] `Entities\Media`, `Image`, `ImageSize`, `MediaFile` and the metadata entities on the mediatheque's contracts; image sizes are `laravel-image` presets (`Image::for($media)->preset(...)->variant()`); protected properties kept
  - [x] `createFromFile` and `updateFromFile` through `Mediatheque::ingest()` and `replaceOriginal()`; the download from a URL, with its limits, stays here
  - [x] the pages models and the JSON media sync on the mediatheque's `HasMedias`, by handle ([json-data.md](json-data.md#syncing))
- [x] Medias module, references: the `media` and `medias` keys and `MediaReferences`, with `site.medias.reference_key` ([decisions/0008](decisions/0008-media-references.md))
- [x] Panneau 1.x removed: its fields and resources, and `folklore/laravel-panneau` 1.x
- [x] The 1.1.8 characterization tests of `JsonDataCast` pass, but three that the spec changes on purpose: a string that isn't a reference of its relation is neither written nor synced as an id, a reference is synced only to the relation of its path, and a media reference resolves before it is attached ([CHANGELOG](../CHANGELOG.md))

## Checks for the pilot's database (2.0.0-alpha.2)

The pilot replaces its prototype with the core. It runs PostgreSQL with `jsonb` columns, which neither this package nor the mediatheque tested. This slice adds checks and docs, not new APIs ([#89](https://github.com/folkloreinc/laravel-folklore/issues/89)). The `Entities` contract stays as it is ([#83](https://github.com/folkloreinc/laravel-folklore/issues/83), closed).

- [x] The test suite on PostgreSQL 17, where the JSON fixtures' columns are `jsonb` ([#87](https://github.com/folkloreinc/laravel-folklore/issues/87))
- [x] `conflict` with `laravel-mediatheque` below 2.0.0-alpha.7: the medias module needs `HasMedias` (alpha.6) and the migrations that run on MySQL (alpha.7), and `suggest` doesn't enforce a version
- [x] The base repository passes its `TEntity` to its contracts, so that a repository contract typed on its entity passes static analysis ([#90](https://github.com/folkloreinc/laravel-folklore/pull/90))
- [x] The `ShortUuid` test that failed at random, on `v2.x` and `v1.1` ([#66](https://github.com/folkloreinc/laravel-folklore/issues/66))
- [x] Docs: the usage graph and the JSON sync settled, and the limits of the repository contract found in the review of the prototype ([architecture.md](architecture.md#review-against-the-pilots-prototype)); `resolveMany()` taking identifiers settled, and the three 1.1.8 tests that change on purpose ([json-data.md](json-data.md))

## JSON references on the cast (2.0.0-alpha.3)

What the pilot needs to move its JSON references onto `JsonDataCast`.

- [x] `MediaReferences`: the forms of references it accepts, with a strict mode ([#85](https://github.com/folkloreinc/laravel-folklore/issues/85))
- [x] `JsonData::stored()`: a JSON column with its references as stored ([#88](https://github.com/folkloreinc/laravel-folklore/issues/88))
- [x] The cast syncs the relations of a model's references when the model is saved anywhere, not only through the base repository, and doesn't refresh it ([#97](https://github.com/folkloreinc/laravel-folklore/issues/97))
- [x] `MediaReferences` resolves media of the database under `Mediatheque::fake()` ([#98](https://github.com/folkloreinc/laravel-folklore/issues/98))

## JSON data in its own package (2.0.0-alpha.4)

JSON data moves to `folklore/laravel-json-data` ([decisions/0021](decisions/0021-json-data-package.md)). 2.0.0-alpha.4 requires its [v1.0.0-alpha.2](https://github.com/folkloreinc/laravel-json-data/releases/tag/v1.0.0-alpha.2), which brings the first slice of the schema of [decisions/0020](decisions/0020-json-schema.md).

- [x] Create the package from this repository's history of the moved files, with its CI (SQLite, MySQL, PostgreSQL `jsonb`), its release process and its docs: the spec and the decisions about JSON data ([folkloreinc/laravel-json-data#2](https://github.com/folkloreinc/laravel-json-data/pull/2))
- [x] Hooks in the package for what it no longer knows: declarations of models without a schema, unwrapping entities, syncing a kind of relation, the models of the rewrite command
- [x] Release its `1.0.0-alpha.1`, with the behaviour of this package's last alpha
- [x] Here: require it, remove the moved code, add the `class_alias`es under the 1.x names and the subclass of the cast with the macros, and register the hooks: the adapter of the 1.x declarations, the medias module, the pages module. The 1.x JSON tests keep passing ([#108](https://github.com/folkloreinc/laravel-folklore/issues/108), [#109](https://github.com/folkloreinc/laravel-folklore/pull/109))
- [x] Transfer the JSON data issues to the package (#84, #86 and #105 are [laravel-json-data#4](https://github.com/folkloreinc/laravel-json-data/issues/4), [#5](https://github.com/folkloreinc/laravel-json-data/issues/5) and [#6](https://github.com/folkloreinc/laravel-json-data/issues/6)), and update the workspace inventory
- [x] Require its `1.0.0-alpha.2`: the first slice of the schema (references, filling from a flat input, unknown keys refused, column extraction), and reference paths with a key and no Eloquent relation ([laravel-json-data#4](https://github.com/folkloreinc/laravel-json-data/issues/4)). Here: `Json::media()`, the site's locales and `JsonData::fill()` in the base repository, which replaces [#104](https://github.com/folkloreinc/laravel-folklore/issues/104); the rewrite command is `json-data:rewrite-references` ([#111](https://github.com/folkloreinc/laravel-folklore/issues/111), [#112](https://github.com/folkloreinc/laravel-folklore/pull/112))

## Panneau 5 adapter and media entities (next)

What the contests platform needs to move its admin to Panneau 5.

- [ ] Panneau 5 plugin, its first part: the `ResourceRepository` adapter on the typed query, `scope()` on the query context, and `ChecksSlugs` (`slugExists()`) for the checks of Panneau's slug fields ([decisions/0018](decisions/0018-panneau-plugin-php-only.md), `laravel-panneau`'s [decision 0011](https://github.com/folkloreinc/laravel-panneau/blob/v5.x/docs/decisions/0011-slug-checks.md), proposed); `laravel-panneau`'s architecture updated for the adapter

Once `laravel-mediatheque` 2 has them:

- [x] `MediaResource` on the mediatheque's canonical resource (its 2.0.0-alpha.10), with the `legacy` shape of 1.1 ([#124](https://github.com/folkloreinc/laravel-folklore/issues/124))
- [ ] The download from a URL moved to the mediatheque, behind its SSRF guard

## Pages

- [ ] Pages module: blocks in the page's JSON, type registry, configurable route names ([decisions/0009](decisions/0009-pages-and-blocks.md))
- [ ] The Panneau plugin's page fields: `Page`, `Pages`, `Blocks` and `PageSlug`, in PHP on Panneau 5's built-in types, with the URL of a slug from the pages module ([decisions/0018](decisions/0018-panneau-plugin-php-only.md)); `PageSlug` on Panneau's generic slug field ([folkloreinc/panneau-js#109](https://github.com/folkloreinc/panneau-js/issues/109))

## Later

Not scheduled yet: each item goes into the slice that needs it. The JSON data items are built in `folklore/laravel-json-data` ([decisions/0021](decisions/0021-json-data-package.md)), and this package requires the alpha that ships them.

- [ ] The next slices of the schema of JSON columns: validation rules, then a JSON Schema export ([laravel-json-data#6](https://github.com/folkloreinc/laravel-json-data/issues/6), [decisions/0020](decisions/0020-json-schema.md))
- [ ] `json-data:sync-references`: sync the relations of stored JSON references, for rows written without model events ([laravel-json-data#5](https://github.com/folkloreinc/laravel-json-data/issues/5), was #86). Needed before the pilot's first import, since the JSON is its source of truth and the tables are indexes, and for upgraded sites
- An idea, not planned: rows computed from JSON entries, for list items that repeat the same related model with different attributes (one person with two roles)

## Beta: upgrade path

- [ ] Auth module, opt-in ([decisions/0010](decisions/0010-users-and-auth.md))
- [ ] Other modules opt-in; Places on Laravel's HTTP client; removed modules gone; paginator override removed ([decisions/0012](decisions/0012-other-modules.md))
- [ ] Deprecated API complete, with notices on the `deprecations` channel, including the 1.x JSON declarations (`HasJsonDataRelations`, `HasJsonDataColumnExtract`, `$jsonAttributeFillable`, `$jsonAttributeExclude`) once the pages models use schemas
- [x] `folklore:upgrade --check`: modules, packages, removed modules, Panneau contracts, the site's own JSON references and their stored paths and keys, block data, the media API, deprecated API ([#123](https://github.com/folkloreinc/laravel-folklore/issues/123))
- [x] `folklore:upgrade`: enables the modules, sets the media reference key, moves the rows of `medias_pivot` that 1.x wrote without a handle under the `json` handle, with `--dry-run` ([#123](https://github.com/folkloreinc/laravel-folklore/issues/123))
- [ ] `folklore:upgrade` converts the block data of `blocks_pivot`, with the pages module of 2.0
- [x] Rector rules: the classes that moved, `$jsonAttributeName`, `new Medias()`, the subclasses of `MediaResource` ([#123](https://github.com/folkloreinc/laravel-folklore/issues/123))
- [ ] An active site upgrades (the contests platform is the candidate)

The auth and other modules are placed in the beta because the site that upgrades needs them; a pilot that needs one earlier pulls it into an alpha.

## Release candidate

- [ ] Public API frozen; contracts documented
- [ ] README rewritten; upgrade guide complete
- [ ] The upgraded site runs the RC in production

## 2.0.0

- [ ] Every item above done, CI green on the whole matrix

## After 2.0.0

- Upgrade of the other active 1.1 sites, usually with their move to Panneau 5
- 3.0, later: remove the deprecated API

## Open questions

- How `Page` restricts the pages it offers to some types, as `withTypes()` did in 1.x: a relation filter in Panneau 5, or a pages resource per type (slice "Pages").
- Whether `MediaReferences` later moves to the mediatheque, as an integration it ships, so that a project gets media references without this package's medias module ([decisions/0021](decisions/0021-json-data-package.md)).

## Pilots' adoption

- Niche moved its core to 2.0.0-alpha.2 and its brands' images to the cast; the rest of its JSON columns follow, on 2.0.0-alpha.4 and `laravel-json-data`.
- The contests platform upgrades during the beta.
