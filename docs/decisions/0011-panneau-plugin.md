# 0011: A Panneau 5 plugin, as an opt-in module

- Status: accepted
- Date: 2026-10-19

## Context

1.x ships Panneau 1.x fields and resources (`Page`, `Blocks`, `PageSlug`, `Medias`), and its contracts extend Panneau 1.x's. Panneau 5 has a new protocol: a `ResourceRepository` with a typed `ResourceQuery`, and fields written in JavaScript. Sites copy `PageSlug` into their own code.

`laravel-panneau`'s architecture planned an adapter over the 1.x `get($params, $page, $count)`.

## Decision

- The plugin is an opt-in module. It requires `folklore/laravel-panneau ^5.0`, which is suggested.
- An **adapter** for Panneau's `ResourceRepository`, built on the typed query of [0003](0003-evolve-in-place.md). The 1.x `get()` is covered by the deprecated API, not by the adapter. `laravel-panneau`'s docs are updated to match.
- Panneau 5's `scope()` maps to the global query context ([0004](0004-global-query-context.md)).
- The `Page`, `Pages`, `Blocks` and `PageSlug` fields, with their JavaScript plugin, so sites stop copying `PageSlug`.
- It must ship before Panneau 5's betas end ([laravel-panneau#35](https://github.com/folkloreinc/laravel-panneau/issues/35), Phase 2).

## Consequences

- The Panneau 1.x fields and resources are removed from 2.0; sites that upgrade move to Panneau 5 at the same time.
- Open, see [roadmap.md](../roadmap.md#open-questions): where the plugin's JavaScript lives (`folklore-js` or this repository).
