# 0004: A global query context that doesn't leak

- Status: accepted
- Date: 2026-10-19

## Context

1.1 repositories can be scoped once with `setGlobalQuery()`, rather than by passing a context to every call, for example to limit them to a tenant. The context is set on the repository instance. A repository is usually a shared instance in the container: in a queue worker or under Octane, the context of one job or request stays on it for the next.

Both pilots need tenant scoping (a workspace, a client), and Panneau 5 scopes its resources with `scope()`.

## Decision

- `setGlobalQuery()` stays.
- `withGlobalQuery([...])` returns a **copy** of the repository with that context, without changing the original.
- A **default context resolver** can be bound in the container, for example the current tenant from the request. It applies automatically to every repository that doesn't have an explicit context.
- Panneau 5's `scope()` maps onto the same mechanism, in the Panneau plugin.

## Consequences

- Code that runs in workers uses `withGlobalQuery()` or the resolver, not `setGlobalQuery()`. The docs say so, and `setGlobalQuery()` may be deprecated later.
- Tenancy stays in the application: the package has no tenant concept, only a context it passes to `buildQueryFromParams()`.
