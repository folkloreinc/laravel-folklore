# Upgrading from 1.1

> **Draft.** This guide is filled as 2.0 lands. The steps and the mapping below are the plan ([decisions/0003](decisions/0003-evolve-in-place.md)). `json-data:rewrite-references`, `folklore:upgrade` with its `--check`, and the Rector rules exist ([#123](https://github.com/folkloreinc/laravel-folklore/issues/123)); the conversion of block data comes with the pages module of 2.0.

2.0 keeps the 1.x class names and their protected API. What it replaces is deprecated until 3.0, so a site can upgrade first and clean up its code afterwards. Sites usually upgrade when they move to Panneau 5, since the Panneau plugin ships with 2.0.

## Before you start

- The site runs **PHP 8.3+ and Laravel 12+** ([decisions/0001](decisions/0001-supported-versions.md)). Upgrade Laravel first if needed.
- The site requires a tagged 1.1 release (`^1.1`), not `v1.1.x-dev`, and is on the latest one.
- The site moves to `laravel-mediatheque` 2, `laravel-image` 2 and Panneau 5 if it uses the matching modules. Their own upgrade guides come first.
- Back up the database.

## Steps

1. **Require the new version:** `composer require folklore/laravel-folklore:^2.0`, and the packages of the modules the site uses (they are suggested, no longer required). During the pre-releases, require `folklore/laravel-folklore:^2.0@alpha` and `folklore/laravel-json-data:^1.0@alpha` together: a stability flag doesn't apply to the dependencies of a required package, so a site with `minimum-stability: stable` can't install the alphas of `laravel-json-data` otherwise.
2. **Check the site:** `php artisan folklore:upgrade --check`. It writes nothing, and exits with an error while something is to do. See [What `--check` reports](#what---check-reports).
3. **Run the Rector rules:** they rename what moved and rewrite the 1.x API that code can follow. See [The Rector rules](#the-rector-rules). Then run `folklore:upgrade --check` again: what is left is to do by hand.
4. **Upgrade:** `php artisan folklore:upgrade --dry-run` says what the upgrade changes; `php artisan folklore:upgrade` does it. It turns on the site's modules in `config/site.php`, sets the media reference key that the site stores (`medias://`) and the 1.1 shape of `MediaResource`, and removes the rows of `medias_pivot` that 1.x wrote without a handle. See [What `folklore:upgrade` does](#what-folkloreupgrade-does). Every module is off by default ([architecture.md](architecture.md#configuration-and-modules)). It runs after the Rector rules: a model that still uses 1.x's `HasMedias` doesn't load, and its rows of `medias_pivot` are left until it does.
5. **Run the site's tests**, and check pages, forms with media, and logins.
6. **Fix the deprecations** at your own pace. They are logged on the `deprecations` channel.
7. **Later:** move media references to `media://` with `php artisan json-data:rewrite-references --from=medias --to=media`, once front ends read both.

## What `--check` reports

It reads the site's code as text (`app`, `config`, `routes`, `bootstrap/app.php` and `bootstrap/providers.php`, or the `--path` given), so it finds the classes that no longer load in 2.0. It reads the JSON columns of the models declared there, or given with `--model`, from the database: the first 1000 rows of each by default, all of them with `--limit=0`. `--json` prints the report as JSON. Each finding is **breaking** (fix it before upgrading), **to do** (while upgrading), **deprecated** (works until 3.0) or **info**.

| Check | What it finds |
|---|---|
| Modules | The modules the site uses and that are off: the classes of each module, and what the 1.1 service provider did for every site: the `routeForReactRouter` macro and the `csv` and `pixel` responses (unless the site defines them itself), the `repository` auth provider, the Customer.io client and transport, the PubNub driver |
| Removed modules | The site manager, organisations, PubSubHubbub, the `Contact`, `Person` and `Contactable` contracts, Panneau 1.x's fields and resources |
| Packages | The packages that 1.1 required and that the site needs without requiring them: the modules' packages, `laravel-locale` for the view composers, Guzzle, `google/apiclient` and `parsecsv` for the services |
| Panneau contracts | Site code that uses `Panneau\Contracts\ResourceItem` or `Repository`, which Folklore's entities and repositories no longer implement |
| JSON references in the code | Models that replace the `JsonDataCast` of their Folklore model with a plain `json` cast, code that writes `scheme://id` references itself, and the pivot syncs of the repositories that use that code |
| Stored JSON references | For each JSON column, the paths and keys of its references (`data.gallery.* → medias:// (12)`): those of a column without `JsonDataCast` are to declare. Keys that aren't mapped, and keys whose identifiers match nothing in the model they map to, such as `media://12` written by id where 2.0 reads `media://` by uid. `medias://` references without `site.medias.reference_key` set to `medias` |
| Block data | The rows of `blocks_pivot`, which 2.0 stores in the page's JSON |
| Medias | `Folklore\Mediatheque\Support\Traits\HasMedias`, `image.sizes` filters, `new ImageSize()` with filters, `setFilters()`, `getMetadatas()`, `new Medias($typeFactory)` |
| Media resource | The site's uses of Folklore's `MediaResource` and `MediasCollection` while `site.medias.resource` isn't `legacy`. In its subclasses: `toArray()` declared without `Request $request` and `: array`, `$this->method()` calls that went to the entity in 1.1, and reads of `$this->resource`, which is the media model in 2.0 |
| Deprecated API | `JsonDataCast::macro()`, `$jsonAttributeName`, `setGlobalQuery()` |

## What `folklore:upgrade` does

It reads the site as `--check` does (`--path`, `--model`, `--limit`), says what it changes, and changes it. `--dry-run` writes nothing. In production, it asks first (`--force` doesn't). It can run again: what is done is left as it is.

- **`config/site.php`:** turns on each module the site uses, as `--check` finds them, unless it is on. A module whose packages are missing stays off, since the site wouldn't boot: require them, and run it again. Sets `medias.reference_key` to `medias` when the JSON columns store `medias://` references, and `medias.resource` to `legacy` when the site uses Folklore's `MediaResource` or `MediasCollection`. It edits the site's file in place, keeping its comments and layout, and starts from the package's `config/site.php` when the site has none. A value that isn't an array literal (`'modules' => $modules`) is left for you to set: the command says which.
- **`medias_pivot`:** 1.x synced the media of JSON references to the mediatheque's pivot without a handle, and 2.0 syncs them under the `json` handle. The command removes the rows without a handle of the models whose JSON data 2.0 syncs: those cast with `JsonDataCast` that use laravel-mediatheque 2's `HasMedias`. A model that replaces the cast with a plain `json` cast syncs its references with its own code, and keeps its rows. Then run `php artisan json-data:sync-references` to write the rows under the `json` handle.
- **Block data:** `blocks_pivot` is listed as left to do. Its rows are converted to the page's JSON with the pages module of 2.0 ([decisions/0009](decisions/0009-pages-and-blocks.md)); keep the table until then.

## The Rector rules

The site requires Rector (`composer require --dev rector/rector`) and adds the set to its `rector.php`:

```php
use Folklore\Rector\FolkloreSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/config', __DIR__.'/routes', __DIR__.'/database'])
    ->withSets([FolkloreSetList::UPGRADE_2_0])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
```

`withImportNames()` imports the new names and removes the old imports; without it, Rector writes the renamed classes fully qualified. Run `vendor/bin/rector process --dry-run` to see the changes, then without `--dry-run`. The set:

| Rule | 1.1 | 2.0 |
|---|---|---|
| Renames | `Folklore\Mediatheque\Support\Traits\HasMedias` | `Folklore\Mediatheque\Concerns\HasMedias` |
| | `Panneau\Contracts\ResourceItem` | `Folklore\Contracts\Entities\Entity` |
| | `Panneau\Contracts\Repository` | `Folklore\Contracts\Repositories\Entities` |
| | `Folklore\Eloquent\JsonData`, `JsonReference`, `JsonReferenceEntry`, `ReferenceContext`, `ReferenceOperation`, `ModelReferences`, `Exceptions\*`, `Folklore\Contracts\Eloquent\ReferenceHandler`, `Folklore\Console\JsonReferencesRewriteCommand` | Their classes in `Folklore\JsonData\` |
| `JsonAttributeNameRector` | `protected $jsonAttributeName = 'data';` in a repository | `protected $jsonAttributes = ['data'];`, unless the repository reads `$this->jsonAttributeName` |
| `MediasRepositoryConstructorRector` | `new Medias($typeFactory)` | `new Medias()` |
| `MediaResourceSubclassRector` | In a subclass of `MediaResource`: `toArray($request)`, `$this->files()`, `$this->resource` | `toArray(Request $request): array`, `$this->entity()->files()`, `$this->entity()` |

The rest of "What changes" isn't mechanical (image sizes as presets, `getMetadatas()`, `JsonDataCast::macro()`, `setGlobalQuery()`, which mutates where `withGlobalQuery()` returns a copy): `--check` lists it.

## What changes

### Dependencies and modules

| 1.1 | 2.0 |
|---|---|
| Requires the mediatheque, `laravel-image`, Panneau 1.x, Fortify, Guzzle, `google/apiclient`, `parsecsv` | Requires `illuminate/*` and `folklore/laravel-json-data`; the rest is suggested per module |
| Everything boots | Modules boot when enabled in `config/site.php` |
| `repository` auth provider and Fortify callbacks always registered | Auth module |
| The mediatheque's model contracts rebound by the package | `config('mediatheque.models')` |
| Global paginator page resolver override | Removed: `OffsetPaginator` resolves its own offset |
| Site manager, organisations, PubSubHubbub | Removed |

### API

| 1.1 | 2.0 | Until 3.0 |
|---|---|---|
| `Entity extends Panneau\Contracts\ResourceItem` | `Entity` on its own | — (breaking) |
| `Entities extends Panneau\Contracts\Repository` | `Entities` on its own | — (breaking) |
| `get($params, $page, $count)` | Typed query | Kept |
| `setGlobalQuery()` | `withGlobalQuery()`, default resolver | Kept |
| `findById()`, `update()`, `destroy()`, `valueById()` ignore the global query | They apply it, without the default params ([decisions/0017](decisions/0017-finds-apply-the-query-context.md)) | — (breaking) |
| `findById()` by `id` | By the model's entity key (`HasEntityKey`), or its key | — |
| `$jsonAttributeName` | `$jsonAttributes` | Kept, deprecated |
| `Folklore\Eloquent\JsonData` and the other JSON data classes | Their classes in `folklore/laravel-json-data` (`Folklore\JsonData\`) | Aliased, deprecated |
| `JsonDataCast::macro(...)`, `get`/`set`/`before_set` options | Reference handlers | Kept, deprecated |
| `lazy` | `withJsonReferences()` | Kept, deprecated |
| Panneau 1.x fields and resources | Panneau 5 plugin | Removed |
| `blocks_pivot` | Blocks in the page's JSON | Converted by `folklore:upgrade` |

### Medias

| 1.1 | 2.0 |
|---|---|
| `laravel-mediatheque` 1.x, `laravel-image` 1.x | Their 2.x: upgrade them first, with their guides |
| The package rebinds the mediatheque's model contracts | `config('mediatheque.models')`: the medias module sets its models there unless the site sets its own |
| `Folklore\Mediatheque\Support\Traits\HasMedias` on site models | `Folklore\Mediatheque\Concerns\HasMedias` |
| Image sizes from `config('image.sizes')` (filters) | `laravel-image` presets, `config('image.presets')` |
| `new ImageSize($image, $filters, $format)` | `new ImageSize($image, 'preset', $format)` |
| `Image::setFilters([...])` | Deprecated: a preset name, or `width`, `height`, `fit`, `format`, `quality` |
| `MediaMetadata::getMetadatas()` | Removed: the entities read the mediatheque's `metadata()` |
| `new Medias($typeFactory)` | `new Medias()` |
| `MediaResource`: the 1.x shape | The mediatheque's canonical shape (`id` is the uid, `files` by handle); `site.medias.resource` set to `legacy`, or `->legacy()`, keeps the 1.x shape |
| A subclass of `MediaResource` overrides `toArray($request)` and calls the entity through the resource (`$this->files()`) | `toArray(Request $request): array`, and `$this->entity()->files()`: the resource is the mediatheque's model |

### JSON data

Stored references don't change. Without `JsonData::enforceMap()`, a key that isn't mapped falls back to the relation of the same name, as in 1.x. See [json-data.md](json-data.md#from-1x).

A site declares nothing new: `getJsonDataRelations()`, `getJsonDataColumnExtract()` and the repositories' `$jsonAttributeFillable` keep working, and are deprecated from the beta on, when `folklore:upgrade` can convert them. A model can move one JSON column at a time to a schema (`jsonDataSchema()`, [json-data.md](json-data.md#schemas)); its repository then fills it with `JsonData::fill()`.

The media referenced in JSON data are synced to the mediatheque's pivot under the `json` handle. 1.x wrote those rows without a handle, and the first sync leaves them: `folklore:upgrade` removes them, or they stay as usages of their media.
