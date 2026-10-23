# 0018: The Panneau plugin is PHP only

- Status: accepted
- Date: 2026-10-23
- Replaces: [0011](0011-panneau-plugin.md)

## Context

[0011](0011-panneau-plugin.md) planned the `Page`, `Pages`, `Blocks` and `PageSlug` fields with their JavaScript plugin, and left open where that JavaScript lives (`folklore-js` or this repository).

In 1.x, only `PageSlug` has a component of its own (`page-slug`): it shows the page's URL in front of the slug, which depends on the parent chosen in the form. `Page`, `Block` and `Blocks` configure Panneau's generic `item` and `items` components from PHP. Panneau 5's protocol has the types they need: `relation`, `repeater` with typed blocks, and `slug`, which already follows another field and shows a fixed prefix.

## Decision

- **The plugin ships no JavaScript.** Its fields extend Panneau 5's built-in types and are configured in PHP:
  - `Page` and `Pages`: a `Relation` to the pages resource, multiple for `Pages`.
  - `Blocks`: a `Repeater` whose `types` are built from the block type registry ([0009](0009-pages-and-blocks.md)). Its maximum depth and excluded types are computed in PHP, as in 1.x.
  - `PageSlug`: Panneau's `Slug`. The pages module gives the URL of a slug from its routes (`home`, `page`, `page_with_parent`) and the page's parent.
- **The slug features go to Panneau.** A source per locale, a URL computed by the backend from the form's values, and checking that a slug is free are proposed as features of Panneau's generic `slug` field, since they aren't specific to pages ([folkloreinc/panneau-js#109](https://github.com/folkloreinc/panneau-js/issues/109)).
- The rest of [0011](0011-panneau-plugin.md) stands: an opt-in module requiring `folklore/laravel-panneau ^5.0` (suggested), the `ResourceRepository` adapter on the typed query, `scope()` on the query context, and shipping before Panneau 5's betas end.

## Consequences

- Sites get the plugin's fields without building any JavaScript of this package. Where the plugin's JavaScript lives is no longer a question.
- `PageSlug` waits for Panneau's `slug` field to have these features. Without them, it shows a fixed prefix and doesn't follow the parent.
- The adapter answers whether a slug is taken for entities, whose repositories aren't Eloquent.
- 1.x's `Page::withTypes()` restricted the pages offered to some types. Panneau 5's `relation` has no filter in its definition, so this is open: a relation filter in Panneau, or a pages resource per type.
