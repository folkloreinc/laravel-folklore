# JSON data in laravel-folklore

JSON columns and their references live in [`folklore/laravel-json-data`](https://github.com/folkloreinc/laravel-json-data), which the core requires since 2.0.0-alpha.4 ([decisions/0021](decisions/0021-json-data-package.md)). Its [json-data.md](https://github.com/folkloreinc/laravel-json-data/blob/v1.x/docs/json-data.md) specifies the cast, the registry of keys, handlers, the schema of JSON columns, syncing, the reference listing and the rewrite command.

This document covers what laravel-folklore adds on top of it: the 1.x API, the medias module's references and sync, the site's locales, and the repositories' JSON columns. The 1.x behaviour is described in [current-state.md](current-state.md#jsondatacast).

## The 1.x API

Sites upgraded from 1.x keep their code; everything below is deprecated and removed in 3.0.

- **Names.** The classes that moved keep their 1.x names as aliases (`src/Folklore/Eloquent/aliases.php`): `Folklore\Eloquent\JsonData`, `JsonReference`, `JsonReferenceEntry`, `ReferenceContext`, `ReferenceOperation`, `ModelReferences`, their exceptions, `Folklore\Contracts\Eloquent\ReferenceHandler` and `Folklore\Console\JsonReferencesRewriteCommand`. New code uses the `Folklore\JsonData\` names.
- **`Folklore\Eloquent\JsonDataCast`** stays a class, a subclass of the package's cast that keeps the 1.x macros (`JsonDataCast::macro('{relation}:get')`, `':set'`, `':before_set'`).
- **Declarations.** A model without a schema keeps `getJsonDataRelations()` and `getJsonDataColumnExtract()` (`HasJsonDataRelations`, `HasJsonDataColumnExtract`): `LegacyJsonDataDeclarations` feeds them to the cast through laravel-json-data's `JsonData::declareUsing()` hook.
- **Entities.** A value that is an entity (`HasModel`, `ToEntity`, `Entity`) is unwrapped to its model through `JsonData::unwrapUsing()`, so an entity can be written at a reference path.

The 1.x declarations aren't marked `@deprecated` yet: the `Page` and `Block` models of the pages module still use them. They get their notices at the beta, when the pages module moves to schemas and `folklore:upgrade` can convert a site ([roadmap.md](roadmap.md#beta-upgrade-path)).

## Syncing media references

With the medias module and `laravel-mediatheque` 2 installed, the cast's sync of a relation to media goes through the mediatheque's `HasMedias::syncMedias()`, set with `JsonData::syncUsing()` (`MediaUsagesSync`). It writes into `medias_pivot`, which is also the mediatheque's usage graph, under the handle `json`: the sync only replaces its own rows, so a media attached otherwise (`attachMedia($media, 'cover')`) stays ([laravel-mediatheque's decision 0020](https://github.com/folkloreinc/laravel-mediatheque/blob/v2.x/docs/decisions/0020-attachments-by-handle-and-usages.md)). One handle serves every JSON column of a model, since the cast gathers the references of a relation across its columns.

`MediaUsagesSync::synced()`, set as the second callback of `JsonData::syncUsing()`, gives the media synced under the `json` handle, so that laravel-json-data's `json-data:sync-references` compares them with the JSON and syncs only the rows that changed, and its `--dry-run` reports the media to attach and to detach. Rows written without model events (imports, `saveQuietly()`, SQL) are synced with that command ([laravel-json-data's docs](https://github.com/folkloreinc/laravel-json-data/blob/v1.x/docs/json-data.md#syncing-stored-rows)).

A reference whose identifier is not a media key (a URL, a reference of another relation) is not synced; 1.x wrote it to the pivot. Rows that 1.x wrote for JSON references have no handle: an upgraded site removes them before the first sync ([upgrade.md](upgrade.md)).

## Rewriting references

laravel-json-data's `json-data:rewrite-references` looks at the models of `app/Models` by default; with the pages module, laravel-folklore adds its `Page` and `Block` (`JsonReferencesRewriteCommand::discoverUsing()`). It was `folklore:json-references:rewrite` until 2.0.0-alpha.4.

## Schemas

A model declares a JSON column with a schema, `jsonDataSchema()` ([decisions/0020](decisions/0020-json-schema.md)), from laravel-json-data's alpha 2. The schema, its nodes and `JsonData::fill()` are specified in [laravel-json-data's json-data.md](https://github.com/folkloreinc/laravel-json-data/blob/v1.x/docs/json-data.md#schema). This package adds:

- **`Json::media()`**, with the medias module: `Json::reference()` with the key that the module writes (`site.medias.reference_key`, [below](#media-references)), synced to the `medias` relation of `HasMedias`, under the `json` handle ([Syncing](#syncing-media-references)). `Json::media()->translatable()` stores one media per locale (`image.*`), as 1.x sites declare them.
- **The site's locales** for translatable nodes: `Folklore\Eloquent\SiteLocaleResolver`, bound as laravel-json-data's `LocaleResolver`, reads `site.locales` when it is set, then laravel-locale's `locale.locales`, then laravel-json-data's `json-data.locales`. The default locale is laravel-json-data's: `json-data.default_locale`, else `app.locale` when it is one of the locales.
- **The base repository fills with `JsonData::fill()`** ([Repositories](#repositories)).

```php
use Folklore\JsonData\Contracts\HasJsonDataSchema;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;

class Game extends Model implements HasJsonDataSchema
{
    use HasMedias;

    protected $casts = ['data' => JsonDataCast::class];

    public function jsonDataSchema(string $attribute): ?ObjectType
    {
        return $attribute === 'data' ? Json::object([
            'title' => Json::string()->translatable()->extract('title_{locale}'),
            'image' => Json::media()->translatable(),
            'prize' => Json::reference('prize')->relation('prizes'),
        ])->additional(false) : null;
    }
}
```

An attribute without a schema keeps the 1.x declarations, `getJsonDataRelations()` and `getJsonDataColumnExtract()`, and the macros, through the declarations hook.

## Media references

The medias module registers two keys, when `laravel-mediatheque` 2 is installed:

| Key | Column | Form | Use |
|---|---|---|---|
| `media` | `uid` | `media://med_01J…` | 2.0 |
| `medias` | `id` | `medias://12` | 1.x, kept for upgraded sites |

Both use `Folklore\Eloquent\MediaReferences`, so either one resolves in a relation to media, attached or not ([decisions/0016](decisions/0016-scope-of-unmapped-keys.md)). The module maps first, and so writes by default, the key of `site.medias.reference_key`: `media` for new projects (the default), `medias` for upgraded sites (`folklore:upgrade` sets it when the site stores `medias://` references), so that front ends that read `medias://` keep working until they move. A relation can still choose with `key`, and a reference of the other key is kept as it is until the rewrite command converts it. A project that only wants `media://` references sets `site.medias.references.other_key` to `false`: the module then maps only the key of `site.medias.reference_key`.

`MediaReferences`:

- `prepare()` accepts a media model, a media entity, a reference of either key, a uid, a 1.x id, a Panneau 5 media value (`['id' => 'med_…']`), an uploaded file, a file or a URL, as far as `site.medias.references.accept` allows ([below](#accepted-forms)). Files are ingested with `Mediatheque::ingest()`, which replaces the `SyncsJsonDataRelations` traits several sites copied. A URL is downloaded with the medias repository's limits (`site.medias.download`), then ingested, until the mediatheque ingests URLs behind its SSRF guard. Files and URLs are ingested when the owner is saved ([decisions/0013](decisions/0013-defer-prepare-until-saving.md)).
- `resolveMany()` runs one query on the key's column through the media model, with its files, as `Mediatheque::resolveMany()` does. It doesn't call the facade, so that references resolve under `Mediatheque::fake()` too ([below](#testing-with-the-mediatheques-fake)).
- Applications plug their tenancy into it:

```php
MediaReferences::ingestUsing(fn (Model $owner) => ['workspace_id' => $owner->workspace_id]);
MediaReferences::allowUsing(fn (Media $media, ?Model $owner) => $media->workspace_id === $owner?->workspace_id);
```

`ingestUsing()` gives the columns of the media ingested for an owner; `allowUsing()` is checked on write (a refusal throws `ReferenceNotAllowed`) and on read (a refused media reads as `null`).

### Accepted forms

A value that reaches a media path, from a form for example, is downloaded by the server if it is a URL, ingested if it is a file, and resolved by internal id if it is a number. `site.medias.references.accept` lists the forms `prepare()` takes ([#85](https://github.com/folkloreinc/laravel-folklore/issues/85)):

| Form | Values |
|---|---|
| `model` | A media model or entity, or a Panneau 5 value whose `id` is a uid |
| `reference` | A `media://med_…` reference |
| `uid` | A uid (`med_…`) |
| `legacy_id` | A 1.x id: `12`, `'12'`, `medias://12`, or a Panneau 5 value whose `id` is one |
| `file` | An uploaded file or a file, ingested |
| `url` | An http(s) URL, downloaded then ingested |

```php
// config/site.php
'medias' => [
    'reference_key' => 'media',
    'references' => [
        'accept' => MediaReferences::STRICT,  // ['model', 'reference', 'uid']
        'other_key' => false,                  // don't map `medias://` (by internal id)
    ],
],
```

- By default (`null`), the forms are `MediaReferences::STRICT` under `JsonData::enforceMap()`, and every form (`MediaReferences::FORMS`) otherwise, as in 1.x, so upgraded sites keep their behaviour.
- A value of a form that isn't accepted throws `InvalidJsonReference` under `enforceMap()`, and is written `null` otherwise, as any value the handler doesn't take. Nothing is downloaded nor ingested.
- An unknown form throws an `InvalidArgumentException`.
- A reference of a mapped key is kept as it is when it is written, without `prepare()` ([Writing](https://github.com/folkloreinc/laravel-json-data/blob/v1.x/docs/json-data.md#when-a-value-is-assigned)). With both keys mapped, `medias://12` is still written under the strict forms: a project that refuses internal ids sets `other_key` to `false` too.
- When URLs are accepted, set `site.medias.download.allowed_hosts`: it is `null` by default, which allows every host.

### Testing with the mediatheque's fake

`Mediatheque::fake()` replaces ingestion, probing and renditions, in memory. `MediaReferences` resolves references from the database, not through the facade, so a media created in the database (with a factory, for example) reads and writes as usual under the fake ([#98](https://github.com/folkloreinc/laravel-folklore/issues/98)). Its `resolve()` and `resolveMany()` know only the media it ingested itself.

A file or a URL written to a media path is ingested by the fake, which returns a `FakeMedia`: it isn't a model, so the value is written `null`. A test that writes files to JSON data creates its media in the database first, or doesn't fake the mediatheque.

## Repositories

- A repository lists its JSON columns (`$jsonAttributes = ['data', 'images']`). The first one takes the data that isn't fillable, as `$jsonAttributeName` (`data`) does in 1.x, which keeps working, deprecated. Each of the others is given as a whole (`['images' => ['logo' => $media]]`) and merged into its stored value: the keys given replace theirs, the others stay, and the references that don't resolve are put back first. `null` clears it.
- **A model with a schema** (one of its JSON attributes has one) is filled with `JsonData::fill()`, which puts the flat input (`title`, `seo_title`) at the paths of its schemas, keeps the paths it doesn't give and the references that don't resolve, and refuses the keys that are neither fillable nor in a schema when its root is `additional(false)`. `$jsonAttributeFillable` and `$jsonAttributeExclude` don't apply to it. An attribute of `$jsonAttributes` without a schema is still given as a whole and merged.
- `withJsonReferences()` returns a copy of the repository that loads the references of each result with `JsonData::load()`, so `lazy` is no longer needed. The repository it is called on doesn't change.

## From 1.x

The changes of the cast itself are in [laravel-json-data's json-data.md](https://github.com/folkloreinc/laravel-json-data/blob/v1.x/docs/json-data.md#from-1x). Those of this package:

| 1.x | 2.0 | Until 3.0 |
|---|---|---|
| `JsonDataCast::macro('{relation}:get')`, `':set'`, `':before_set'`, per model or global | Handlers per key, `using` per path | Macros run, deprecated |
| One JSON column per repository (`$jsonAttributeName`) | `$jsonAttributes` | Kept, deprecated |
| `getJsonDataRelations()`, `getJsonDataColumnExtract()` | `jsonDataSchema()` | Kept for the attributes without a schema |
| `$jsonAttributeFillable`, `$jsonAttributeExclude` (`'*'`: every key that isn't fillable) | `JsonData::fill()`, from the schema; unknown keys refused under `additional(false)` | Kept for the models without a schema |
| Synced by the base repository, after `saveData()` | Synced by the cast after any save with events; the base repository's `syncRelations($model, $data)` stays as a hook for its own relations | — |
| `syncRelations()` then `refresh()` | Relations and memoized values unset | — |
| Media references written to `medias_pivot` without a handle | Synced under the `json` handle | — |

Every 1.1 test of the cast passes on 2.0 without changes, except those that assert a deprecated behaviour's notice. The characterization tests added in 1.1.8 ([#60](https://github.com/folkloreinc/laravel-folklore/pull/60)) are the reference. Three of them change on purpose, as the breaking changes of 2.0.0-alpha.1 in the [CHANGELOG](../CHANGELOG.md) say: a string at a reference path that isn't a reference of its relation is neither written nor synced as an id, a reference is synced only to the relation of its path, and a media reference resolves by its key before it is attached.
