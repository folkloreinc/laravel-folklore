# 0022: The page fields: a registry of block types, and types of pages by resource

- Status: accepted
- Date: 2026-10-29
- Issue: [#122](https://github.com/folkloreinc/laravel-folklore/issues/122)

## Context

[0018](0018-panneau-plugin-php-only.md) puts the `Page`, `Pages`, `Blocks` and `PageSlug` fields on Panneau 5's built-in types, in PHP. Building them left four questions:

- **Types of pages.** In 1.x, `Page::withTypes()` restricted the pages offered to some types by adding `type` to the picker's request. Panneau 5's `relation` has no filter in its definition: it lists the related resource's items, within its scope, and validates the ids it receives against that scope.
- **Types of blocks.** In 1.x, the `Blocks` field read its types from the `blocks` resource of Panneau 1.x, whose types were classes. Panneau 5's resources have no types, and the type registry of [0009](0009-pages-and-blocks.md), which maps types to entity classes, isn't built yet. A site offers the same types in most of its fields: Folklore's 1.x pilot has 18 types of blocks, used by about 20 fields.
- **Nesting.** Blocks contain blocks (a stack, a card, an accordion). In 1.x, `maxDepth()` dropped the types whose blocks would be too deep, and `withoutType()` excluded types by class. Panneau 5 builds a definition at once, so a type that can contain itself recurses forever without a limit.
- **Storage.** Blocks are still rows of `blocks`, linked through `blocks_pivot`, until the pages module moves them into the page's JSON. The repository updates a block when it receives its `id`, and creates one otherwise. Panneau keeps only the declared values of a block.

## Decision

- **`Page` and `Pages` restrict types with the related resource's scope.** They are relations to the `pages` resource by default, multiple for `Pages`. A field that offers some types of pages relates to a pages resource whose `scope()` has these types (`['type' => ['section']]`): its picker lists them only, and Panneau refuses the ids of other pages when saving. The pages repository reads the `type` param (`$queryColumns`). There is no `withTypes()`: a filter on the relation would need a change of the protocol, and wouldn't be enforced by the server without one in `laravel-panneau` too. A site with many sets of types may ask for one later.
- **A registry of block types.** `Folklore\Panneau\BlockTypes`, a singleton of the panneau module, has the site's Panneau `BlockType`s, registered as instances or as closures called when a field first needs them, in a request, so that labels are translated in its locale. A `Blocks` field offers the registry's types, or those given to `types()`, narrowed by id with `onlyTypes()` and `exceptTypes()`. It is separate from the type registry of 0009: that one maps types to entity classes, this one gives their fields to the admin. They use the same ids.
- **Nesting is limited by levels.** A type that has a `Blocks` field among its fields nests blocks. `maxDepth($levels)` counts levels from the outermost field, whose blocks are the first, as in 1.x; the smallest limit of a field and of the fields it is nested in applies, and the limit is 3 levels when no field sets one. A type whose blocks would be deeper than the limit isn't offered, as in 1.x. The nested fields are copies of the type's at the next level.
- **Blocks keep their id.** `Blocks` reads the item's `blocks()`, block entities, models or arrays, and gives each block its `id`. When it is saved, a block keeps its id only if it is one of the item's blocks, at any level, and no other block of the save has kept it; otherwise the repository creates a new block. A block of another page is never updated through this one.
- **Relations inside blocks are references.** `Blocks` turns the values of the relation fields of a block into references, as Panneau does at the top of an item: Panneau's serializer doesn't look into repeaters ([folkloreinc/laravel-panneau#117](https://github.com/folkloreinc/laravel-panneau/issues/117)). It goes away once Panneau does it.
- **`PageSlug` is Panneau's `Slug`**, localized and from the `title` by default. Its check gives the page's URL from the routes of the pages module, `Pages\PageRoutes`, whose names `site.pages.routes` sets (`home`, `page`, `page_with_parent`). The URL follows the form's `parent` (`parentField()`), and the field is disabled for the home page. `unique()` is opt-in, since it needs the repository's `ChecksSlugs` trait.

## Consequences

- Sites declare a resource per set of types of pages that a field offers, a subclass of their pages resource with its `scope()`, and register their block types once instead of a resource of blocks.
- The picker of a `Page` field shows the pages of its resource only, and the server refuses the others, unlike 1.x, where the filter was the client's.
- Saving a page updates its blocks rather than creating new rows, while they are stored through `blocks_pivot`. Moving blocks into the page's JSON doesn't change the fields: blocks there are arrays, with an id or without.
- Nested definitions grow with each level: the default limit keeps a site with a few types that nest to a definition of a reasonable size.
- Fields that relate to pages inside blocks cost a query per value when an item is read, until Panneau serializes nested references in batches.
