# 0015: Repositories find by `id`, or by route key when they opt in

- Status: accepted
- Date: 2026-10-22

## Context

In 1.x, `findById()`, `update()` and `destroy()` find a model with `where('id', $id)`, through `findModelById()`. Sites pass integer ids, and some models have another route key: the pages module's `Page` routes by `slug_{locale}`.

The pilot's prototype of the core finds by the model's route key, its public uid (`med_…`, `wsp_…`), and its repositories call `parent::findById()` with uids. `ModelEntity::id()` returns the route key, so an entity's id and the id a repository finds by are the same value only when both use the route key.

Finding by route key everywhere would change what `findById()` finds for every model whose route key isn't `id`, starting with pages.

## Decision

- `findById()` keeps finding by **`id`** by default.
- A repository **opts in to the route key** with `protected $findByRouteKey = true;`. It then finds by `$model->getRouteKeyName()`.
- The same column is used everywhere the base class finds a model by public id: `findById()`, `update()`, `destroy()` and `valueById()` (through `findModelById()`), and the `ids` and `except` of `EntitiesQuery`.
- The property is untyped, like the other protected properties of the base class, so that a site can redeclare it without a type ([0003](0003-evolve-in-place.md)).
- Implemented in alpha 1, with the base class's other 2.0 changes.

## Consequences

- 1.x sites see no change.
- The pilot's repositories set `$findByRouteKey` and swap from the prototype without changing their calls.
- A new repository on `ModelEntity` should opt in, so that `findById($entity->id())` finds its entity. The `make:repository` generator can set it when it generates an entity on `ModelEntity`.
- The `identifier` param of 1.x (numeric to `id`, otherwise `handle`) doesn't change.
