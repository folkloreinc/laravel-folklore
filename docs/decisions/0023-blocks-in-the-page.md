# 0023: Blocks in the page's JSON, typed by a schema

- Status: accepted
- Date: 2026-11-03
- Implements: [0009](0009-pages-and-blocks.md) (blocks in the page's JSON, type registry)
- Amends: [0022](0022-page-fields.md) (storage of the `Blocks` field)

## Context

[0009](0009-pages-and-blocks.md) moves a page's blocks into its JSON, and [0022](0022-page-fields.md) built the Panneau fields while blocks were still rows of `blocks`, linked through `blocks_pivot`. Three questions were left open:

- **Typing.** A block's data depends on its type. laravel-json-data's schema had no node whose shape depends on a value, so a list of blocks could only be open (`Json::any()`, `paths()`), neither closed nor validated per type.
- **Transition.** Sites on the alphas (and the 1.1 sites that upgrade) store their blocks as rows. Keeping both storages in the module would mean two read and write paths until 3.0.
- **Identity.** A row had an id. Panneau keeps a block's id between saves (0022), and front ends key blocks by it.

## Decision

- **Blocks are objects in the page's `data.blocks`**, and nested blocks in their block's `blocks`, typed by a union of laravel-json-data (`Json::union('type', …)`, laravel-json-data 1.0.0-alpha.6). Each type is a closed object, built down to a depth (3 levels by default), so the cast declares the references of every type at every level (`blocks.*.image`, `blocks.*.blocks.*.image`), `JsonData::fill()` puts each block into its type, and `Json::rules()` checks each block with the rules of its type.
- **A registry of block types**, `site.pages.blocks.types`, maps each type to its entity class, which extends `Folklore\Entities\Block` and gives the schema of its data (`BlockType`). The page's schema builds its union from it. It is the type registry of 0009, and the Panneau `BlockTypes` of 0022 keep giving the admin its fields, with the same ids.
- **Every block has an `id`**, a string kept between saves: the pages repository gives one (`blk_` and a ULID) to a block that has none. A block may have a `handle`. Both are written in every type by the union, beside the type's own properties.
- **Block entities are built from the block's object**, not from a model: `id()`, `type()`, `handle()`, `data()` and `blocks()`. They don't implement `HasModel`; a page's version covers its blocks, since they are in its data.
- **Pages refer to pages by the `page` key**, mapped by the module to `site.pages.model` (`Folklore\Models\Page` by default). The page's schema has `title` and `description` (translatable), `image` (`Json::media()`), `parent` (a `page` reference synced to `parent`) and `blocks`. A site's page model adds its own properties to these, and closes the root when it maps all its keys (`JsonData::enforceMap()`).
- **A breaking change of the alpha, with a conversion.** The module reads and writes blocks in the JSON only. `folklore:upgrade` converts the stored data: each `blocks://` reference of a page's `blocks`, at every level, becomes the block's object (its row's id as its `id`, its `type`, its `handle`, its data), and `parent://` becomes `page://`. The media of the blocks are then synced to the page under the `json` handle. `--dry-run` says what it would convert. The `Block` model, the `Blocks` repository and the `blocks` and `blocks_pivot` tables stay, deprecated, for the conversion; a site drops the tables once it has converted.

## Consequences

- A site declares its block types once, in `site.pages.blocks.types`, with their entity classes and schemas, instead of `$entitiesByType` on a `Block` model.
- A block entity that read its model (`$this->model->handle`) reads its data (`$this->handle()`, `$this->data`).
- A site that queried blocks on their own, or attached blocks to models other than pages, keeps those rows: `folklore:upgrade --check` lists them, and they aren't converted.
- Saving a page is one write; reading it needs no join, and its references resolve in one query per key for a whole list of pages (`withJsonReferences()`).
- The ids of converted blocks are their former row ids, as strings, so that anchors and links stay valid; new blocks get a ULID.
