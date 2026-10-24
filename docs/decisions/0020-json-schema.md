# 0020: A fluent schema describes each JSON column

- Status: proposed
- Date: 2026-10-23
- Issue: [#105](https://github.com/folkloreinc/laravel-folklore/issues/105), which replaces [#104](https://github.com/folkloreinc/laravel-folklore/issues/104)
- Package: `folklore/laravel-json-data`, where JSON data moves ([0021](0021-json-data-package.md))

## Context

A column cast with `JsonDataCast` is described in three places that don't know each other:

- `getJsonDataRelations()` (`HasJsonDataRelations`): which paths hold references, and the relation they sync to;
- `getJsonDataColumnExtract()` (`HasJsonDataColumnExtract`): which paths are copied into columns, to sort or search on them;
- the base repository's `$jsonAttributeName`, `$jsonAttributeFillable` and `$jsonAttributeExclude`: which input keys go into the column, and at which path.

The last one lives only in the base repository. A model saved by another repository has to split its input by hand ([#104](https://github.com/folkloreinc/laravel-folklore/issues/104)), as it had to sync its relations by hand before alpha.3 ([#97](https://github.com/folkloreinc/laravel-folklore/issues/97)). Its default, `'*'`, stores every key that isn't fillable, so a typo or an unexpected field ends up in the column without anything saying so.

Nothing describes the shape of the column itself: which keys it may hold, their types, which ones are translatable. Form requests repeat that shape by hand. API specs and front-end types can't be checked against the stored data.

Sites keep their labels and descriptive content in a JSON column (a name, a list of benefits, a description) and their filters and sorts in columns, behind a flat API. Clients send `name`, never `data.name`, and the repository decides where it goes. Some sites build their fields from data, such as a content type that an editor defines. Their schema exists only at runtime.

## Decision

**The schema is built in `folklore/laravel-json-data`**, the package JSON data moves to ([0021](0021-json-data-package.md)), not in this package. Its slices are released as alphas of that package, and this package requires them. What the schema needs from this package (media references, the base repository) is added here, on top of it.

**The schema is the only way the package declares a JSON column.** The 1.x declarations (`getJsonDataRelations()`, `getJsonDataColumnExtract()`, the macros) stay in this package, which feeds them to the package's cast through a hook ([0021](0021-json-data-package.md)).

**A schema per JSON attribute, declared on the model instance.** A model implements `HasJsonDataSchema` and builds its schemas with a fluent factory:

```php
use Folklore\JsonData\Contracts\HasJsonDataSchema;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;

class Offer extends Model implements HasJsonDataSchema
{
    public function jsonDataSchema(string $attribute): ?ObjectType
    {
        return match ($attribute) {
            'data' => Json::object([
                'name' => Json::string()->translatable()->required()->max(120),
                'benefits' => Json::list(Json::string()->max(200))->max(10),
                'seo' => Json::object([
                    'title' => Json::string()->input('seo_title'),
                ]),
                'image' => Json::media(),
                'prize' => Json::reference('prize')->relation('prizes'),
                'source' => Json::reference('revision'),
                'body' => Json::document()->references('media'),
                'sort_name' => Json::string()->extract('name_sort'),
            ])->additional(false),
            default => null,
        };
    }
}
```

The method runs on the instance, so a schema can depend on the model's data: the fields of its type, for example. The cast memoizes the schema per instance and attribute. For a model that returns `null` for an attribute, or has no schema, the cast asks the declarations hook: in a site on this package, its 1.x declarations.

### Nodes

| Node | Stored as | Notes |
|---|---|---|
| `Json::string()`, `integer()`, `number()`, `boolean()` | The JSON scalar | `min()`, `max()`, `pattern()` |
| `Json::date()`, `Json::datetime()` | An ISO 8601 string | |
| `Json::enum(BackedEnum::class)` or `Json::enum([...])` | The enum's value | |
| `Json::object([...])` | An object | `additional(bool)`: whether keys outside the schema are kept |
| `Json::list(node)`, `Json::map(node)` | An array, or an object with any keys | `min()`, `max()` |
| `Json::any()` | Any value, not looked into | For data the site doesn't describe yet |
| `Json::media()` | A media reference (`media://…`) | A macro that this package's medias module adds to `Json`: `Json::reference()` with the key the module writes ([0008](0008-media-references.md)), synced to `medias`. The JSON data package doesn't know media |
| `Json::reference(key)` | `{key}://{identifier}` | `relation()` to sync it. Without one, it is resolved and checked but not synced: the key-only paths of [#84](https://github.com/folkloreinc/laravel-folklore/issues/84) |
| `Json::document()` | A document (ProseMirror JSON…) | `references(key, …)`: references at any depth, as `**` |

Every node has `required()`, `nullable()` and `default()`. A scalar node can be `translatable()`: it is stored as an object keyed by locale (`{"fr": "…", "en": "…"}`). It can also be read from an input key with another name, `input('seo_title')`, and copied into a column, `extract('name_sort')`.

### What derives from it

- **References.** The cast reads the reference paths, their keys and their relations from the schema. It compiles them into the same declarations as the hook's, so a column declared either way behaves the same. A key given to a node must be mapped ([0005](0005-json-reference-keys.md)), and its handler applies ([0006](0006-reference-handlers.md)).
- **Filling.** `JsonData::fill(Model $model, array $input): void` puts the input keys that the schema declares at their paths. The model's fillable attributes stay Eloquent's.
  - A partial update replaces the paths it gives and keeps the others. `null` clears a path. The references that don't resolve are put back first, as the repositories do since 1.1.8.
  - A string given for a translatable node goes into the default locale. An object keyed by locale replaces the locales it gives.
  - A key that is neither fillable nor in the schema is refused (`InvalidJsonData`) when its object is `additional(false)`, which is the default under `JsonData::enforceMap()`. Otherwise it is ignored, as `fill()` ignores an attribute that isn't fillable.
  - This package's base repository uses `JsonData::fill()` for a model that has a schema, instead of `$jsonAttributeFillable`.
- **Column extraction.** `extract()` does what `getJsonDataColumnExtract()` does in this package: the column is a copy, written when the model is saved.
- **Validation.** `Json::rules(Model $model, string $attribute): array` compiles the schema into Laravel validation rules on the flat input keys (`name`, `name.fr`, `benefits.*`, `seo_title`), for form requests. The core gets no dependency. A schema can also be checked when the model is saved (`->validatesOnSave()`), to guarantee the stored shape whatever wrote it.
- **Export.** `toJsonSchema(): array` gives a standard JSON Schema (draft 2020-12), with `x-folklore-reference` (the key) and `x-folklore-translatable` extensions. Sites can test their API specs against it, generate front-end types from it, and, after 2.0, the Panneau plugin can build fields from it.

### Locales

The locales of `translatable()` and the default one come from the JSON data package's config, falling back to `app.locale`, or from a resolver bound in the container, since the package doesn't depend on `laravel-locale`. This package binds one that reads `site.locales` when it is set.

### Slices

Each slice is an alpha of the JSON data package. Its alpha 1 is the extraction, without a schema ([0021](0021-json-data-package.md)), so slice 1 is its alpha 2.

1. The declaration, references, `JsonData::fill()`, unknown keys and `extract()`. This is [#104](https://github.com/folkloreinc/laravel-folklore/issues/104), on a schema instead of a separate `HasJsonDataFields`.
2. `Json::rules()` and `validatesOnSave()`.
3. `toJsonSchema()`.

## Consequences

- A JSON column is declared once. `HasJsonDataRelations`, `HasJsonDataColumnExtract`, the macros and the base repository's JSON properties stay in this package and keep working for the attributes that have no schema, deprecated until this package's 3.0. `folklore:upgrade --check` reports them. The JSON data package knows only the schema.
- Under `enforceMap()`, a key can no longer slip into a column unnoticed. Sites that keep the 1.x behaviour declare `additional(true)`, or don't declare a schema.
- Key-only references ([#84](https://github.com/folkloreinc/laravel-folklore/issues/84)) come from `Json::reference()` without a relation, without another option on paths.
- A list of objects with a reference could later declare rows computed from its entries (one person with two roles), the idea under "Later" in the roadmap, as an option of `Json::list()`.
- The schema is a public API of the JSON data package, to keep stable through its 1.x, and the cast calls it for every model it reads or writes. Building it must stay cheap, hence the memoization per instance.
- The exported JSON Schema says what a reference's key points to, which JavaScript readers of references need ([0005](0005-json-reference-keys.md)).

## Open questions

- Validation: compiled Laravel rules only, or a JSON Schema validator in `suggest` (`opis/json-schema`) for `validatesOnSave()`.
- Whether reading a translated value (`name` in a locale, with a fallback) belongs here, or stays in the entities.
- Names (`HasJsonDataSchema`, `jsonDataSchema()`, `Json`, `ObjectType`, `InvalidJsonData`, `input()`, `extract()`) and their namespace in the package are proposals until the first slice is merged, as in [0014](0014-names.md).
