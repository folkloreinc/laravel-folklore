# 0003: Evolve the 1.x classes in place, deprecated until 3.0

- Status: accepted
- Date: 2026-10-19

## Context

The active sites on 1.1 have about 130 repositories and 700 entity classes built on the package's base classes. They override protected methods, most of them untyped (`newModel`, `newQuery`, `buildQueryFromParams`, `getDefaultParams`, `fillModel`, `fillModelJsonAttributes`, `saveData`, `syncRelations`). They read protected properties of the entities (`$model`, `$data`, `$metadata`, `$sizes`, `$files`). Production data stores `relation://id` references in JSON columns, and some sites read them outside PHP.

A clean break (new namespaces, new base classes) would give a cleaner API, but every site would rewrite its repositories and entities before it could move to Panneau 5, which ships its plugin with 2.0.

## Decision

- 2.0 **keeps the names**: `Repositories\Entities`, `Entities\*`, `HasTypedEntity`, `to_entity()`, `to_id()`, and the `order`, `offset` and `identifier` params.
- **The only break in the type hierarchy:** `Entity` no longer extends Panneau's `ResourceItem`, and the `Entities` repository contract no longer extends Panneau's `Repository`. Panneau 5 requires it.
- **New typed methods are added next to the old ones.** For example a typed query, close to Panneau 5's `ResourceQuery` (filters, sorts, search, pagination, `ids`, `except`), next to `get($params, $page, $count)`, and an optional typed base entity.
- **Existing methods are typed through PHPDoc** and checked by Larastan. Native types on methods that sites override without types would be a fatal error when those sites load.
- **What 2.0 replaces is deprecated** on the same classes, logged on the `deprecations` channel, and removed in 3.0. Rector rules and `folklore:upgrade --check` help with the move.
- **Compatibility target:** the 1.1 test suite passes on 2.0, except what covers Panneau 1.x and the removed modules. One active site upgrades during the beta.

## Consequences

- A child class can add native types to the methods it overrides: the pilot's prototype of the core, written with native types, stays compatible.
- New code in the package is fully typed; only the signatures sites override stay untyped.
- The 1.1 tests are kept on `v2.x` and run in CI, as the compatibility suite.
- 3.0 removes the deprecated API, later, with its own Rector rules.
