# laravel-folklore 2

`folklore/laravel-folklore` is the shared foundation of the Laravel sites built by Folklore: entities and repositories over Eloquent models, JSON columns through [`folklore/laravel-json-data`](https://github.com/folkloreinc/laravel-json-data), and opt-in modules for medias, pages, auth, Panneau and third-party services.

Version 2 is in development on the `v2.x` branch, released as alphas (see the [CHANGELOG](../CHANGELOG.md)). It builds on `laravel-mediatheque` 2, `laravel-image` 2 and Panneau 5, with a core that depends on none of them. Its pilots are Niche (the core and `JsonDataCast`) and the contests platform (`JsonDataCast`, pages, the Panneau plugin and the upgrade of an existing site). 1.x stays maintained on `v1.1` for fixes and security.

| Document | Content |
|---|---|
| [current-state.md](current-state.md) | Audit of 1.1: dependencies, public API, `JsonDataCast`, known limits |
| [architecture.md](architecture.md) | Design of 2.0: core, modules, entities, repositories, query context, compatibility |
| [json-data.md](json-data.md) | What this package adds to `folklore/laravel-json-data`: the 1.x API, `MediaReferences`, the repositories' JSON columns. The JSON data itself is specified in [laravel-json-data's docs](https://github.com/folkloreinc/laravel-json-data/blob/v1.x/docs/json-data.md) ([decisions/0021](decisions/0021-json-data-package.md)) |
| [decisions/](decisions/) | One file per decision |
| [roadmap.md](roadmap.md) | The plan of 2.0: slices, what each release shipped, open questions |
| [upgrade.md](upgrade.md) | Upgrading a site from 1.1 (draft, filled as 2.0 lands) |

Tracking issues: [#113](https://github.com/folkloreinc/laravel-folklore/issues/113) (2.0, the index of [roadmap.md](roadmap.md)) and [#1](https://github.com/folkloreinc/laravel-folklore/issues/1) (1.x).

Related work:

- `laravel-mediatheque` 2: [folkloreinc/laravel-mediatheque#1](https://github.com/folkloreinc/laravel-mediatheque/issues/1)
- `laravel-image` 2: [folkloreinc/laravel-image#3](https://github.com/folkloreinc/laravel-image/issues/3)
- Panneau 5: [folkloreinc/laravel-panneau#35](https://github.com/folkloreinc/laravel-panneau/issues/35)
