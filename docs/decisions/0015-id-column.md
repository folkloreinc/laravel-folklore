# 0015: Repositories find by a configurable id column, `id` by default

- Status: accepted
- Date: 2026-10-22

## Context

In 1.x, `findById()`, `update()` and `destroy()` find a model with `where('id', $id)`, through `findModelById()`. Sites pass integer ids.

The pilot's prototype of the core finds by the model's route key, its public uid (`wsp_…`), and its repositories call `parent::findById()` with uids. Its `ModelEntity::id()` returns the route key.

Finding by the route key was considered, everywhere or as an opt-in. The route key belongs to routing and can change at runtime: the pages module's `Page` routes by `slug_{locale}`, which depends on the current locale. A repository would then find by a different column in each language, and changing a model's URLs would change what its repository finds.

## Decision

- The base repository finds by a configurable column, **`protected $idColumn = 'id';`**. A repository whose public id is another column sets it (`'uid'`).
- The same column is used everywhere the base class finds a model by public id: `findById()`, `update()`, `destroy()` and `valueById()` (through `findModelById()`), and the `ids` and `except` of `EntitiesQuery`.
- The property is untyped, like the other protected properties of the base class, so that a site can redeclare it without a type ([0003](0003-evolve-in-place.md)).
- The entity's `id()` and the repository's column must name the same value. A **contract test**, that sites and the package run against their repositories, checks that `findById($entity->id())` finds the entity, for an entity created through the repository.
- Implemented in alpha 1, with the base class's other 2.0 changes.

## Consequences

- 1.x sites see no change.
- The pilot's repositories and entities set `$idColumn = 'uid'` and swap from the prototype without changing their calls.
- `ModelEntity` reads its id from the same kind of column, `protected string $idColumn = 'id';`, rather than from the route key as the prototype did. An entity and its repository set the same column; the contract test catches a mismatch. The `make:entity` and `make:repository` generators set both.
- The `identifier` param of 1.x (numeric to `id`, otherwise `handle`) doesn't change.
