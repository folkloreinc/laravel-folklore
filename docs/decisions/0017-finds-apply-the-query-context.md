# 0017: Finds apply the query context

- Status: accepted
- Date: 2026-10-23

## Context

In 1.x, a repository's global query (`setGlobalQuery()`) applies to `get()`, `count()`, `has()`, `pluck()` and `value()`, but not to `findById()`, `update()`, `destroy()` or `valueById()`: `findModelById()` passes its params to `newQuery()`, which takes none. A repository scoped to a tenant can still find, update or delete another tenant's row by id.

2.0 adds `withGlobalQuery()` and a default context resolver ([0004](0004-global-query-context.md)), which both pilots use for tenant isolation (a workspace, a client). A context that only scopes lists doesn't isolate anything.

## Decision

- `findById()`, `update()`, `destroy()` and `valueById()` apply the repository's query context: the default resolver's context, then the global query (`setGlobalQuery()`, `withGlobalQuery()`). The typed query (`queryEntities()`) does too.
- Finds don't apply the default params (`getDefaultParams()`): those shape lists (an order, a `published` filter), and a find by id must still find an unpublished page.
- The context's params go through `buildQueryFromParams()`, as for lists.

## Consequences

- A scoped repository only finds, updates and deletes its own rows.
- **Breaking for a site that set a global query and relied on finds ignoring it**, for example an admin that scopes lists but opens any row by id. It drops the context for those calls (a repository without `withGlobalQuery()`). The upgrade guide lists it.
- The `Users` repository's finds (`retrieveById()`, the `repository` auth provider) are scoped too: with a tenant resolver, a user is only found within the current tenant.
