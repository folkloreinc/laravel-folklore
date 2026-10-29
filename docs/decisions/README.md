# Decisions

One file per decision. A decision is not edited once accepted: a later one replaces it and links to it. A proposed decision can still change before it is accepted.

Decisions 0002 to 0004 and 0009 to 0012 come from the plan of 2.0 ([#56](https://github.com/folkloreinc/laravel-folklore/issues/56#issuecomment-6271513875), 2026-10-19). Decisions 0005 to 0008 come from the `JsonDataCast` design of 2026-10-22 ([#56](https://github.com/folkloreinc/laravel-folklore/issues/56#issuecomment-6344885526)), which updated decision 4 of the plan. Decisions 0013 to 0015 settle open points of phase 0, 0016 to 0019 ones of alpha 1, 0020 and 0021 come from the schema of JSON columns ([#105](https://github.com/folkloreinc/laravel-folklore/issues/105), now [laravel-json-data#6](https://github.com/folkloreinc/laravel-json-data/issues/6)), and 0022 from the page fields of the Panneau plugin ([#122](https://github.com/folkloreinc/laravel-folklore/issues/122)).

| # | Decision | Status |
|---|---|---|
| [0001](0001-supported-versions.md) | PHP 8.3+ and Laravel 12+ | Accepted |
| [0002](0002-core-and-modules.md) | A dependency-free core and opt-in modules | Accepted, amended by 0021 |
| [0003](0003-evolve-in-place.md) | Evolve the 1.x classes in place, deprecated until 3.0 | Accepted |
| [0004](0004-global-query-context.md) | A global query context that doesn't leak | Accepted |
| [0005](0005-json-reference-keys.md) | JSON reference keys in a global registry | Accepted |
| [0006](0006-reference-handlers.md) | Reference handlers per key | Accepted |
| [0007](0007-batch-resolution-and-listing.md) | Batch resolution, memoization and reference listing | Accepted |
| [0008](0008-media-references.md) | Media references in the medias module | Accepted |
| [0009](0009-pages-and-blocks.md) | Pages store their blocks in their JSON, with a type registry | Accepted |
| [0010](0010-users-and-auth.md) | Users and auth are an explicit opt-in module | Accepted |
| [0011](0011-panneau-plugin.md) | A Panneau 5 plugin, as an opt-in module | Replaced by 0018 |
| [0012](0012-other-modules.md) | Other modules: kept, deprecated, removed | Accepted |
| [0013](0013-defer-prepare-until-saving.md) | Prepare JSON references when the model is saved | Accepted |
| [0014](0014-names.md) | Names of the JSON data and query context API | Accepted, amended by 0021 and [laravel-json-data's 0022](https://github.com/folkloreinc/laravel-json-data/blob/v1.x/docs/decisions/0022-command-names.md) (the rewrite command is `json-data:rewrite-references`) |
| [0015](0015-entity-key.md) | Models say which column holds their entity's public id | Accepted |
| [0016](0016-scope-of-unmapped-keys.md) | Unmapped keys keep the scope of 1.x; mapped keys resolve by their column | Accepted |
| [0017](0017-finds-apply-the-query-context.md) | Finds apply the query context | Accepted |
| [0018](0018-panneau-plugin-php-only.md) | The Panneau plugin is PHP only | Accepted |
| [0019](0019-media-of-failed-saves.md) | Media ingested by a failed save outside a transaction aren't pruned | Accepted |
| [0020](0020-json-schema.md) | A fluent schema describes each JSON column | Accepted |
| [0021](0021-json-data-package.md) | JSON data moves to its own package | Accepted; open points settled with the extraction ("Since then") |
| [0022](0022-page-fields.md) | The page fields: a registry of block types, and types of pages by resource | Accepted |

Template:

```markdown
# NNNN: Title

- Status: proposed | accepted | replaced by NNNN
- Date: YYYY-MM-DD

## Context
## Decision
## Consequences
```
