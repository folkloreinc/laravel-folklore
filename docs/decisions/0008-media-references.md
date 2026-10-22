# 0008: Media references in the medias module

- Status: accepted
- Date: 2026-10-19, updated 2026-10-22

## Context

1.x stores `medias://{id}` in JSON data. `laravel-mediatheque` 2 exposes media by uid (`med_…`), refers to them with `media://med_…`, and resolves both forms with `MediaReference` and `Mediatheque::resolveMany()` ([its decision 0003](https://github.com/folkloreinc/laravel-mediatheque/blob/v2.x/docs/decisions/0003-identifiers-and-references.md)). Some front ends of upgraded sites read `medias://` outside PHP.

## Decision

- The medias module maps two keys, when `laravel-mediatheque` 2 is installed: `media` (by `uid`) and `medias` (by `id`), both handled by `MediaReferences`.
- Reads accept both in a relation to media.
- Writes use `media://` by default for new projects, and `medias://` for upgraded sites, by setting (the order in which the module maps the keys). A relation can choose with `key`.
- `MediaReferences::prepare()` accepts a media, an entity, a reference, a uid, a 1.x id, a Panneau 5 value, an uploaded file or a URL; files and URLs go through `Mediatheque::ingest()`. `resolveMany()` goes through `Mediatheque::resolveMany()`.
- Applications plug their tenancy with `MediaReferences::ingestUsing()` and `allowUsing()`.
- The handler ships in **alpha 1**, before the rest of the medias module (entities, resources, `createFromFile`), which ships in alpha 2.

## Consequences

- Alpha 1 requires `laravel-mediatheque ^2.0@alpha` in `require-dev`, and suggests it.
- Sites move from `medias://` to `media://` with `folklore:json-references:rewrite`, when their front ends are ready.
- Open: how syncing to `medias_pivot`, which is also the mediatheque's usage graph, avoids detaching media attached otherwise ([json-data.md](../json-data.md#syncing)).
