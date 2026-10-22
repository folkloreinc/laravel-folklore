# laravel-folklore 2

`folklore/laravel-folklore` is the shared foundation of the Laravel sites built by Folklore: entities and repositories over Eloquent models, references in JSON columns (`JsonDataCast`), and opt-in modules for medias, pages, auth, Panneau and third-party services.

Version 2 is in development on the `v2.x` branch. It builds on `laravel-mediatheque` 2, `laravel-image` 2 and Panneau 5, with a core that depends on none of them. Its pilots are Niche (the core and `JsonDataCast`) and the contests platform (`JsonDataCast`, pages, the Panneau plugin and the upgrade of an existing site). 1.x stays maintained on `v1.1` for fixes and security.

| Document | Content |
|---|---|
| [current-state.md](current-state.md) | Audit of 1.1: dependencies, public API, `JsonDataCast`, known limits |
| [architecture.md](architecture.md) | Design of 2.0: core, modules, entities, repositories, query context, compatibility |
| [json-data.md](json-data.md) | Specification of `JsonDataCast` in 2.0: reference keys, handlers, batch resolution, listing, rewriting |
| [decisions/](decisions/) | One file per decision |
| [roadmap.md](roadmap.md) | Phases, alpha slices and open questions |
| [upgrade.md](upgrade.md) | Upgrading a site from 1.1 (draft, filled as 2.0 lands) |

Tracking issues: [#1](https://github.com/folkloreinc/laravel-folklore/issues/1) (overall roadmap) and [#56](https://github.com/folkloreinc/laravel-folklore/issues/56) (plan of 2.0).

Related work:

- `laravel-mediatheque` 2: [folkloreinc/laravel-mediatheque#1](https://github.com/folkloreinc/laravel-mediatheque/issues/1)
- `laravel-image` 2: [folkloreinc/laravel-image#3](https://github.com/folkloreinc/laravel-image/issues/3)
- Panneau 5: [folkloreinc/laravel-panneau#35](https://github.com/folkloreinc/laravel-panneau/issues/35)
