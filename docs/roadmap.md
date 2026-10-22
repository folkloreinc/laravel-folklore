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
- [ ] Tooling: PSR-4, PHP 8.3+, Laravel 12+, CI matrix (PHP 8.3 to 8.5 × Laravel 12 and 13, SQLite and MySQL), Pint, Larastan at the maximum level for new code (the 1.x baseline stays for 1.x code)
- [x] Release process: changelog, `v2.0.0-alpha.N` tags from a green CI, as `laravel-image` 2 does (see "Releases" in [AGENTS.md](../AGENTS.md))
- [ ] Contracts and their tests: `Entity`, `HasModel`, `ToEntity` without Panneau; the `Entities` repository contract and its typed query; the query context resolver; `JsonData`, `JsonReference`, `ReferenceHandler`, `ReferenceContext`
- [ ] Review the contracts against the pilot's prototype of the core and against `laravel-mediatheque` 2's references and usage graph

## Alpha 1: the core and `JsonDataCast`

What both pilots need first: Niche's brand images, and the contests platform before it rewrites its JSON data.

- [ ] Composer: the core requires only `illuminate/*`; the Folklore packages, Fortify, Guzzle, `parsecsv` and `pubnub` move to `suggest`; `google/apiclient` and Panneau 1.x are dropped ([decisions/0002](decisions/0002-core-and-modules.md))
- [ ] Modules switched in `config/site.php`; modules that alpha 1 doesn't port yet stay off and don't load their 1.x dependencies
- [ ] Contracts without Panneau 1.x; optional typed base entity; typed query next to `get()`; PHPDoc types on the 1.x methods
- [ ] Global query context: `withGlobalQuery()`, default resolver ([decisions/0004](decisions/0004-global-query-context.md))
- [ ] `make:entity` and `make:repository` on the 2.0 bases
- [ ] `JsonDataCast` ([json-data.md](json-data.md), decisions [0005](decisions/0005-json-reference-keys.md) to [0007](decisions/0007-batch-resolution-and-listing.md)):
  - [ ] registry of keys, `map()`, `enforceMap()`, per-relation `key`, fallback to the relation name
  - [ ] handlers per key, default handler, `using` per path; 1.x macros and callables through the registry, deprecated
  - [ ] batch resolution (`JsonData::load()`, `withJsonReferences()`), memoization
  - [ ] reference listing, `JsonData::parse()`
  - [ ] recursive wildcard `**`
  - [ ] repositories with several JSON columns
  - [ ] `folklore:json-references:rewrite`
- [ ] Medias module, references only: the `media` and `medias` keys and `MediaReferences` on `laravel-mediatheque ^2.0@alpha` ([decisions/0008](decisions/0008-media-references.md))
- [ ] The 1.1.8 characterization tests of `JsonDataCast` pass unchanged
- [ ] Niche replaces its prototype with `^2.0@alpha`

## Alpha 2: medias

- [ ] `Entities\Media`, `Image` and `ImageSize` on the mediatheque's contracts and `laravel-image` 2, with their protected properties
- [ ] `MediaResource` on the mediatheque's canonical resource, with the `legacy` shape
- [ ] `createFromFile` and `createFromPath` through `Mediatheque::ingest()`; URL download moved to the mediatheque
- [ ] Models declared in `config('mediatheque.models')`

## Alpha 3: pages and the Panneau 5 plugin

Must ship before Panneau 5's betas end ([laravel-panneau#35](https://github.com/folkloreinc/laravel-panneau/issues/35)).

- [ ] Pages module: blocks in the page's JSON, type registry, configurable route names ([decisions/0009](decisions/0009-pages-and-blocks.md))
- [ ] Panneau plugin: `ResourceRepository` adapter on the typed query, `scope()`, the `Page`, `Pages`, `Blocks` and `PageSlug` fields and their JavaScript ([decisions/0011](decisions/0011-panneau-plugin.md))
- [ ] `laravel-panneau`'s architecture updated for the adapter

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

To settle before alpha 1 ships:

- **Scope of the default handler:** resolve unmapped keys through the owner's relation, as 1.x did, or always by column, with scoping left to `allows()` ([json-data.md](json-data.md#batch-resolution)).
- **`resolveMany()` signature:** references, as in the #56 design, or identifiers with a context, as in [json-data.md](json-data.md#handlers).
- **Syncing to `medias_pivot`** without detaching media attached otherwise ([json-data.md](json-data.md#syncing)).
- **`findById()` by route key**, as the pilot's prototype does, or by `id`, as 1.x does, for the typed base classes.
- **Names of the typed query and the typed base entity**, with the contracts ([decisions/0014](decisions/0014-names.md)).
- **Media ingested by a save that fails outside a transaction:** whether the mediatheque prunes media that were never used, through its usage graph ([json-data.md](json-data.md#when-the-save-fails-afterwards)).

Later:

- Where the Panneau plugin's JavaScript lives: `folklore-js` or this repository (alpha 3).
- Whether a 1.2 accepts `^1.1|^2.0` of the mediatheque and `laravel-image`, so that sites upgrade them before this package (decided at the mediatheque's beta).
