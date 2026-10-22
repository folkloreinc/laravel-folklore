# 0009: Pages store their blocks in their JSON, with a type registry

- Status: accepted
- Date: 2026-10-19

## Context

In 1.x, a page's blocks are rows of a `blocks` table, linked through `blocks_pivot`, and also referenced from `data.blocks` with `blocks://{id}`. Saving a page creates or updates each nested block, one query per block. Deleted references leave orphan blocks. Blocks are typed through `$entitiesByType` on the `Block` model, which sites extend. Route names (`home`, `page`, `page_with_parent`) are hardcoded in the `Page` entity, and sites copy the `PageSlug` field of the Panneau 1.x plugin.

## Decision

- **Blocks are stored in the page's JSON.** `blocks_pivot` and orphan blocks go away; `folklore:upgrade` converts existing data.
- The `slug_{locale}` columns stay.
- Route names become configurable.
- **Type registry:** page and block types map to entity classes, in `config/site.php` or in code. `HasTypedEntity` reads `$entitiesByType` on the model first, then the registry. The Panneau plugin builds its block types from it.
- Pages are an opt-in module ([0002](0002-core-and-modules.md)), shipped in alpha 3 with the Panneau plugin ([0011](0011-panneau-plugin.md)).

## Consequences

- A page and its blocks are saved in one write, and read without a join.
- References inside blocks (media, pages) are declared with a recursive or wildcard path on the page ([json-data.md](../json-data.md)).
- Sites that query blocks on their own, outside a page, need a migration path; `folklore:upgrade --check` lists them.
