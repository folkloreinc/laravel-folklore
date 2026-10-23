# 0015: Models say which column holds their entity's public id

- Status: accepted
- Date: 2026-10-22

## Context

In 1.x, `findById()`, `update()` and `destroy()` find a model with `where('id', $id)`, through `findModelById()`. Sites pass integer ids.

The pilot's prototype of the core finds by the model's route key, its public uid (`wsp_…`), and its repositories call `parent::findById()` with uids. Its `ModelEntity::id()` returns the route key. The pilot's models all get their uid, and their route key, from one trait.

Two options were set aside:

- **The route key.** It belongs to routing and can change at runtime: the pages module's `Page` routes by `slug_{locale}`, which depends on the current locale. A repository would then find by a different column in each language, and changing a model's URLs would change what its repository finds.
- **A column set on the repository and on the entity** (`$idColumn`). The same column would be declared in two places, on every repository and every entity of a model, and could disagree.

## Decision

- A model whose entity's public id is not its key implements **`Contracts\Entities\HasEntityKey`**: `getEntityKeyName(): string`, the column, and `getEntityKey(): mixed`, its value. It mirrors `getRouteKeyName()` and `getRouteKey()`.
- **Repositories** find by `getEntityKeyName()` when the model implements it, and by the model's key name (`id`) otherwise. The same column is used everywhere the base class finds a model by public id: `findById()`, `update()`, `destroy()` and `valueById()` (through `findModelById()`), and the `ids` and `except` of `EntitiesQuery`.
- **`ModelEntity::id()`** returns `getEntityKey()` when the model implements it, and the model's key otherwise.
- The contract is separate from `ToEntity`: adding methods to `ToEntity` would break the 1.x models that implement it.
- The package ships no trait for it: its only default would be the model's key, which is what a model that doesn't implement the contract gets.
- A **contract test**, that sites and the package run against their repositories, checks that `findById($entity->id())` finds an entity created through the repository.
- The repository side is implemented in alpha 1, with the base class's other 2.0 changes. The contract and `ModelEntity` are in phase 0.

## Consequences

- 1.x sites see no change: their models don't implement the contract, and their key is `id`.
- The entity and its repository read the same model method, so they can't disagree.
- The pilot implements the contract once, in the trait that gives its models their uid, and swaps from the prototype without changing its repositories, its entities or their calls.
- The `identifier` param of 1.x (numeric to `id`, otherwise `handle`) doesn't change.
