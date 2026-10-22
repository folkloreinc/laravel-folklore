# Decisions

One file per decision. A decision is not edited once accepted: a later one replaces it and links to it. A proposed decision can still change before it is accepted.

Decisions 0002 to 0004 and 0009 to 0012 come from the plan of 2.0 ([#56](https://github.com/folkloreinc/laravel-folklore/issues/56#issuecomment-6271513875), 2026-10-19). Decisions 0005 to 0008 come from the `JsonDataCast` design of 2026-10-22 ([#56](https://github.com/folkloreinc/laravel-folklore/issues/56#issuecomment-6344885526)), which updated decision 4 of the plan. Decisions 0013 to 0015 settle open points of phase 0.

| # | Decision | Status |
|---|---|---|
| [0001](0001-supported-versions.md) | PHP 8.3+ and Laravel 12+ | Accepted |
| [0002](0002-core-and-modules.md) | A dependency-free core and opt-in modules | Accepted |
| [0003](0003-evolve-in-place.md) | Evolve the 1.x classes in place, deprecated until 3.0 | Accepted |
| [0004](0004-global-query-context.md) | A global query context that doesn't leak | Accepted |
| [0005](0005-json-reference-keys.md) | JSON reference keys in a global registry | Accepted |
| [0006](0006-reference-handlers.md) | Reference handlers per key | Accepted |
| [0007](0007-batch-resolution-and-listing.md) | Batch resolution, memoization and reference listing | Accepted |
| [0008](0008-media-references.md) | Media references in the medias module | Accepted |
| [0009](0009-pages-and-blocks.md) | Pages store their blocks in their JSON, with a type registry | Accepted |
| [0010](0010-users-and-auth.md) | Users and auth are an explicit opt-in module | Accepted |
| [0011](0011-panneau-plugin.md) | A Panneau 5 plugin, as an opt-in module | Accepted |
| [0012](0012-other-modules.md) | Other modules: kept, deprecated, removed | Accepted |
| [0013](0013-defer-prepare-until-saving.md) | Prepare JSON references when the model is saved | Accepted |
| [0014](0014-names.md) | Names of the JSON data and query context API | Accepted |
| [0015](0015-find-by-id-or-route-key.md) | Repositories find by `id`, or by route key when they opt in | Accepted |

Template:

```markdown
# NNNN: Title

- Status: proposed | accepted | replaced by NNNN
- Date: YYYY-MM-DD

## Context
## Decision
## Consequences
```
