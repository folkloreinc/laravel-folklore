# 0002: A dependency-free core and opt-in modules

- Status: accepted, amended by [0021](0021-json-data-package.md) (JSON data moves to its own package)
- Date: 2026-10-19

## Context

1.1 requires `laravel-mediatheque` 1.x, `laravel-image` 1.x, `laravel-panneau` 1.x, Fortify, Guzzle, `google/apiclient` and `parsecsv`, and boots most of its features in its service provider whether a site uses them or not (see [current-state.md](../current-state.md)). Its `Entity` contract extends Panneau 1.x's `ResourceItem`.

So a project that only needs entities, repositories and `JsonDataCast` pulls the 1.x line of every other Folklore package, and can't move to their new majors. The pilot project removed 1.1 for that reason, and waits for 2.0.

Splitting the modules into separate packages was considered. Sites use most of them together, and one package with one version is simpler to upgrade than six.

## Decision

- 2.0 stays **one package**: the shared foundation of the Folklore Laravel sites.
- The **core** requires only `illuminate/*`: entities, repositories, the global query context and `JsonDataCast`.
- Everything else is an **opt-in module** in the same package: medias, pages, users and auth, the Panneau 5 plugin, Customer.io, Google, PubNub, the site helpers ([0012](0012-other-modules.md)).
- `folklore/laravel-mediatheque ^2.0`, `folklore/laravel-image ^2.0`, `folklore/laravel-panneau ^5.0`, `laravel/fortify`, `guzzlehttp/guzzle` and `parsecsv/php-parsecsv` move to `suggest`.
- A module boots only when it is enabled in the configuration. It checks that what it needs is installed, and fails with a clear message when it isn't.
- A module a site doesn't enable costs it nothing: no binding, route, macro, middleware, listener or global override.

## Consequences

- The pilot can require `^2.0@alpha` for the core without pulling any 1.x package.
- Each module has its own tests, run with and without its dependencies installed.
- `folklore:upgrade` enables, for an upgraded site, the modules 1.1 booted by default and that the site uses.
- Code that 1.1 shipped in the core but that depends on another package (`Entities\Media`, `Page` with `HasMedias`) moves into the matching module.
