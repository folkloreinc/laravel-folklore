# Upgrading from 1.1

> **Draft.** This guide is filled as 2.0 lands. The steps and the mapping below are the plan ([decisions/0003](decisions/0003-evolve-in-place.md)). `json-data:rewrite-references`, `folklore:upgrade` with its `--check`, and the Rector rules exist ([#123](https://github.com/folkloreinc/laravel-folklore/issues/123)); the conversion of block data comes with the pages module of 2.0.

2.0 keeps the 1.x class names and their protected API. What it replaces is deprecated until 3.0, so a site can upgrade first and clean up its code afterwards. Sites usually upgrade when they move to Panneau 5, since the Panneau plugin ships with 2.0.

## Before you start

- The site runs **PHP 8.3+ and Laravel 12+** ([decisions/0001](decisions/0001-supported-versions.md)). Upgrade Laravel first if needed.
- The site requires a tagged 1.1 release (`^1.1`), not `v1.1.x-dev`, and is on the latest one.
- The site moves to `laravel-mediatheque` 2, `laravel-image` 2 and Panneau 5 if it uses the matching modules. Their own upgrade guides come first. In particular, the mediatheque's database is converted before `folklore:upgrade --check` runs: see step 2.
- Back up the database.

## Steps

1. **Require the new version:** `composer require folklore/laravel-folklore:^2.0`, and the packages of the modules the site uses (they are suggested, no longer required). During the pre-releases, require `folklore/laravel-folklore:^2.0@alpha` and `folklore/laravel-json-data:^1.0@alpha` together: a stability flag doesn't apply to the dependencies of a required package, so a site with `minimum-stability: stable` can't install the alphas of `laravel-json-data` otherwise. The site keeps laravel-panneau 1.x until it moves its admin to Panneau 5, but its 1.x admin stops working: 2.0 removes Panneau 1.x's fields, or gives their names to Panneau 5 fields. The admin's code that runs at boot can stop artisan from starting, `--check` included (`package:discover` fails with `Class ... not found`). `PageSlug::setRoutesResolver()` doesn't: while laravel-panneau 1.x is installed, 2.0 keeps it as a method that does nothing. Any other class of 1.x's `Folklore\Panneau\` at boot does: remove that code before running `--check`.
2. **Check the site:** `php artisan folklore:upgrade --check`. It writes nothing, and exits with an error while something is to do. See [What `--check` reports](#what---check-reports). A site with media upgrades the mediatheque first, following its guide: `php artisan mediatheque:upgrade --check`, `php artisan mediatheque:install --upgrade`, `php artisan migrate`, then `php artisan mediatheque:upgrade`. Before that, the media table has no `uid` column, so `--check` can't look up the `media://` references stored in JSON data: it says to upgrade the mediatheque and run it again, and already reports the integer identifiers under `media://` as breaking, since 2.0 reads `media://` by uid.
3. **Run the Rector rules:** they rename what moved and rewrite the 1.x API that code can follow. See [The Rector rules](#the-rector-rules). Then run `folklore:upgrade --check` again: what is left is to do by hand.
4. **Upgrade:** `php artisan folklore:upgrade --dry-run` says what the upgrade changes; `php artisan folklore:upgrade` does it. It turns on the site's modules in `config/site.php`, sets the media reference key that the site stores (`medias://`), and moves the rows of `medias_pivot` that 1.x wrote without a handle for JSON data under the `json` handle. Run it locally, against a copy of the database, and commit `config/site.php`: on a server, the next deploy replaces the file. Then run it on each environment's database for `medias_pivot`; the config part has nothing left to do there. See [What `folklore:upgrade` does](#what-folkloreupgrade-does). Every module is off by default ([architecture.md](architecture.md#configuration-and-modules)). It runs after the Rector rules: with a mediatheque older than 2.0.0-alpha.11, which has no alias of 1.x's `HasMedias`, a model that still uses it doesn't load, and its rows of `medias_pivot` are left until it does.
5. **Run the site's tests**, and check pages, forms with media, and logins.
6. **Fix the deprecations** at your own pace. They are logged on the `deprecations` channel.
7. **Later:** move media references to `media://` with `php artisan json-data:rewrite-references --from=medias --to=media`, once front ends read both.

## What `--check` reports

It reads the site's code as text (`app`, `config`, `routes`, `resources/views`, `bootstrap/app.php` and `bootstrap/providers.php`, or the `--path` given), so it finds the classes that no longer load in 2.0. Whichever paths are given, it also reads the deployment and build files at the site's root for the commands of the modules: `Envoy.blade.php`, `composer.json`, `package.json`, the workflows of `.github/workflows`, `.gitlab-ci.yml`, `deploy*.sh` and `Dockerfile*`. Then it reads the JSON columns of the models from the database: the first 1000 rows of each by default, all of them with `--limit=0`. The models are the classes of those files that look like models (they extend a model, or have casts), and those given with `--model`. Each one is loaded in a child PHP process first: a class that is a fatal error in 2.0, such as a signature that no longer matches its parent's, is reported instead of stopping the check. `--json` prints the report as JSON. Each finding is **breaking** (fix it before upgrading), **to do** (while upgrading), **deprecated** (works until 3.0) or **info**.

| Check | What it finds |
|---|---|
| Modules | The modules the site uses and that are off: the classes of each module, and what the 1.1 service provider did for every site: the `routeForReactRouter` macro and the `csv` and `pixel` responses (unless the site defines them itself), the `repository` auth provider, the Customer.io client and transport, the PubNub driver. The commands that 2.0 registers only with their module, `assets:view` and `users:create`, called from the code or run by the deployment and build files (`php artisan assets:view` in `Envoy.blade.php`): without the module, the build fails |
| Removed modules | The site manager, organisations, PubSubHubbub, the `Contact`, `Person` and `Contactable` contracts, Panneau 1.x's fields and resources. Without Panneau 5, the 1.x fields `Blocks`, `Page` and `PageSlug`, whose names are Panneau 5 fields in 2.0. As to do: the calls to `PageSlug::setRoutesResolver()`, which does nothing in 2.0 |
| Packages | The packages that 1.1 required and that the site needs without requiring them: the modules' packages, `laravel-locale` for the view composers, Guzzle, `google/apiclient` and `parsecsv` for the services |
| Panneau contracts | Site code that uses `Panneau\Contracts\ResourceItem` or `Repository`, which Folklore's entities and repositories no longer implement, the site's contracts that extend them included, and the `use` statements of them that the Rector set leaves unused |
| JSON references in the code | Models that replace the `JsonDataCast` of their Folklore model with a plain `json` cast, and code that writes `scheme://id` references itself (`'media://'.$id`). As info: code with the key in a variable (`$key.'://'.$id`), which may build URLs, and the `sync()` calls of that code and of the classes that use it or extend it |
| Stored JSON references | For each JSON column, the paths and keys of its references (`data.gallery.* → medias:// (12)`): those of a column without `JsonDataCast` are to declare. Keys that aren't mapped, and keys whose identifiers match nothing in the model they map to, such as `media://12` written by id where 2.0 reads `media://` by uid. `medias://` references without `site.medias.reference_key` set to `medias`. Before the mediatheque is upgraded, its media table has no `uid`: the check says to upgrade it first, and reports integer identifiers under `media://` as breaking without looking them up |
| Block data | The rows of `blocks_pivot`, which 2.0 stores in the page's JSON |
| Medias | `Folklore\Mediatheque\Support\Traits\HasMedias`: deprecated where the installed mediatheque keeps it as an alias (2.0.0-alpha.11 and later), breaking otherwise. `image.sizes` filters, `new ImageSize()` with filters, `setFilters()`, `getMetadatas()`, `new Medias($typeFactory)`. `MediaResource` with `site.medias.resource` set to `canonical`, and its subclasses that override `toArray($request)` without types or call the entity through the resource |
| Deprecated API | `JsonDataCast::macro()`, `$jsonAttributeName`, `setGlobalQuery()` |

## What `folklore:upgrade` does

It reads the site as `--check` does (`--path`, `--model`, `--limit`), says what it changes, and changes it. `--dry-run` writes nothing, and says what it would leave to you. In production, it asks first (`--force` doesn't). It can run again: what is done is left as it is. When the configuration is cached, run `php artisan config:cache` again after it.

- **`config/site.php`:** turns on each module the site uses, as `--check` finds them, unless it is on. A module whose packages are missing stays off, since the site wouldn't boot: require them, and run it again. Sets `medias.reference_key` to `medias` when the JSON columns store `medias://` references. It edits the site's file in place, keeping its comments and layout, and starts from the package's `config/site.php` when the site has none. A value that isn't a literal (`'medias' => env('FOLKLORE_MEDIAS', false)`), or a key under a value that isn't an array literal (`'modules' => $modules`), is the site's: the command leaves it, and says what to set. `medias.resource` is `legacy` by default, so the command leaves it.
- **`medias_pivot`:** 1.x synced the media of JSON references to the mediatheque's pivot without a handle, and 2.0 syncs them under the `json` handle. For each row of the models whose JSON data 2.0 syncs to media (cast with `JsonDataCast`, with laravel-mediatheque 2's `HasMedias`, and a `medias` relation declared in `getJsonDataRelations()` or a schema, without its own `sync`), the command syncs the media that the row's JSON references under the `json` handle, then removes the rows without a handle of those media. A media attached without a handle by other code stays, and so do the rows of a model that replaces the cast with a plain `json` cast, which its own code syncs. The pages module's `Page` and `Block` are read too when the site uses the module.
- **Block data:** `blocks_pivot` is listed as left to do. Its rows are converted to the page's JSON with the pages module of 2.0 ([decisions/0009](decisions/0009-pages-and-blocks.md)); keep the table until then.

## The Rector rules

The site requires Rector (`composer require --dev rector/rector`) and adds the set to its `rector.php`:

```php
use Folklore\Rector\FolkloreSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/config', __DIR__.'/routes', __DIR__.'/database'])
    ->withSets([FolkloreSetList::UPGRADE_2_0]);
```

Run `vendor/bin/rector process --dry-run` to see the changes, then without `--dry-run`. Rector writes the new names fully qualified (`\Folklore\JsonData\JsonData::map()`, `implements \Folklore\Contracts\Entities\Entity`) and leaves the old `use` statements, now unused; `--check` reports those of the Panneau contracts until they are removed. Remove them by hand, or with Pint on the files Rector changed, with only its rule of unused imports: a whole preset would restyle the files of a site that isn't formatted with Pint.

```bash
echo '{"preset": "empty", "rules": {"no_unused_imports": true}}' > pint-imports.json
vendor/bin/pint --dirty --config=pint-imports.json && rm pint-imports.json
```

`--dirty` formats the files that git sees as changed, so run Rector on a clean working tree. `->withImportNames(importShortClasses: false, removeUnusedImports: true)` would import the new names and remove the old imports, but it also imports every fully qualified name of every file Rector reads, not only those of the files the set changes: on a whole site, the upgrade is lost in style changes. The set:

| Rule | 1.1 | 2.0 |
|---|---|---|
| Renames | `Folklore\Mediatheque\Support\Traits\HasMedias` | `Folklore\Mediatheque\Concerns\HasMedias` |
| | `Folklore\Eloquent\JsonData`, `JsonReference`, `JsonReferenceEntry`, `ReferenceContext`, `ReferenceOperation`, `ModelReferences`, `Exceptions\*`, `Folklore\Contracts\Eloquent\ReferenceHandler`, `Folklore\Console\JsonReferencesRewriteCommand` | Their classes in `Folklore\JsonData\` |
| `PanneauContractTypesRector` | `Panneau\Contracts\ResourceItem`, `Panneau\Contracts\Repository` as a parameter, return or property type, in `instanceof`, in the `extends` of an interface or the `implements` of a class (`interface Order extends Entity, ResourceItem`) | `Folklore\Contracts\Entities\Entity`, `Folklore\Contracts\Repositories\Entities`. In `extends` and `implements`, the Panneau contract is removed where Folklore's is already there, through another interface or the parent class. Otherwise `ResourceItem` is replaced by `Entity`, which declares the same `id()`, and `Repository` stays, so `--check` reports it: `Entities` asks for more methods, with other signatures (`create($data)`), which the classes that implement it must have first. `Repository::class` stays: a container binding of it would become a binding of Folklore's contract, so `--check` reports it |
| `JsonAttributeNameRector` | `protected $jsonAttributeName = 'data';` in a repository | `protected $jsonAttributes = ['data'];`. The first one stays next to it, unless the repository is final and doesn't read it: a subclass may read `$this->jsonAttributeName` |
| `MediasRepositoryConstructorRector` | `new Medias($typeFactory)` | `new Medias()` |
| `MediaResourceSubclassRector` | In a subclass of `MediaResource`: `toArray($request)`, `$this->files()`, `$this->resource` | `toArray(Request $request): array`, `$this->entity()->files()`, `$this->entity()`. Writes of `$this->resource` (`=`, `??=`, references, `isset()`, `unset()`) stay |

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

### Panneau 5

The resources of Panneau 5 point to the site's repository contracts, which the panneau module adapts (`site.modules.panneau`, [architecture.md](architecture.md#repository-adapter)). Before moving a resource:

- **`order` is a list of pairs.** The adapter's lists go through the typed query, whose sorts reach `buildQueryFromParams()` as `'order' => [['title', 'asc'], ['published_at', 'desc']]`. The base repository reads it, but a repository that reads `$params['order']` itself as a column, or as one `[column, direction]` pair (`orderBy($params['order'][0], $params['order'][1])`), doesn't sort, or fails: read the pairs, or leave `order` to the base repository.
- **The scope is the repository's params.** `scope()` returns params that the repository's `buildQueryFromParams()` reads (a `queryColumns` entry). The adapter doesn't write them on create: the resource sets the values in `creating()`.
- **Search is opt-in.** A repository that reads `$params['search']` implements `Contracts\Repositories\SearchesEntities`; the others get no search in Panneau.
- **Unique slugs** need the `Repositories\Concerns\ChecksSlugs` trait on the repository.
- `Testing\PanneauRepositoryContractTests` checks each repository through the adapter.

The page fields of 1.x have Panneau 5 equivalents in `Folklore\Panneau\Fields` ([architecture.md](architecture.md#page-fields), [decisions/0022](decisions/0022-page-fields.md)):

| 1.x | 2.0 |
|-----|-----|
| The block types of the `blocks` resource (`$types`, `ResourceType` classes) | Panneau `BlockType`s, registered once with `BlockTypes::register()` |
| `Blocks::make('blocks')` | The same, a `repeater` of the registry's types |
| `->withoutType(StackBlock::class)` | `->exceptTypes('stack')`, by id; `onlyTypes()` keeps some |
| `->maxDepth(2)`, `BlockWithBlocks` | `->maxDepth(2)`, levels counted from the field that sets it, as in 1.x; 3 levels from the outermost field when no field sets one |
| `->withoutNesting()` | `->maxDepth(1)` |
| `->withPlacements([...])` | A subclass of `Blocks` that adds the field to each type in `blockTypes()` ([architecture.md](architecture.md#page-fields)) |
| `Page::make('section')->withTypes(['section'])` | `Page::make('section')->resource(SectionsResource::class)`, a pages resource whose `scope()` is `['type' => ['section']]` |
| `->withQuery([...])` on `Page` and `Pages` | The related resource's `scope()` |
| `Pages::make('pages')` (`items-resource`) | `Pages::make('pages')`, a multiple relation |
| `PageSlugLocalized::make('slug')` | `PageSlug::make('slug')`, localized by default |
| `PageSlug::setRoutesResolver(...)` | `site.pages.routes`, or `url()` on the field for other routes. While laravel-panneau 1.x is installed, the method is kept so that the site boots, but does nothing; Panneau 5's `PageSlug` doesn't have it: remove the call |
| `->isDisabled()` on the home page's slug | Disabled for the page whose `handle` is `home` |
| Slugs not checked (`'unique' => false` in the model's `sluggable()`) | `PageSlug` is unique, across the types of pages; `notUnique()` turns it off |

Blocks keep working through `blocks_pivot`: the field gives each block its id, and saving a page updates its blocks. The pages repository now implements `ChecksSlugs`: a site's subclass of it with its own `slugExists()` must match that contract. Media inside blocks aren't media references yet ([folkloreinc/laravel-panneau#117](https://github.com/folkloreinc/laravel-panneau/issues/117)).

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
| `MediaResource`: the 1.x shape | The 1.x shape by default, on the mediatheque's resource; `site.medias.resource` set to `canonical`, or `->canonical()`, gives the mediatheque's shape (`id` is the uid, `files` by handle), for front ends that moved to it |
| A subclass of `MediaResource` overrides `toArray($request)` and calls the entity through the resource (`$this->files()`) | `toArray(Request $request): array`, and `$this->entity()->files()`: the resource is the mediatheque's model |

### JSON data

Stored references don't change. Without `JsonData::enforceMap()`, a key that isn't mapped falls back to the relation of the same name, as in 1.x. See [json-data.md](json-data.md#from-1x).

A site declares nothing new: `getJsonDataRelations()`, `getJsonDataColumnExtract()` and the repositories' `$jsonAttributeFillable` keep working, and are deprecated from the beta on, when `folklore:upgrade` can convert them. A model can move one JSON column at a time to a schema (`jsonDataSchema()`, [json-data.md](json-data.md#schemas)); its repository then fills it with `JsonData::fill()`.

The media referenced in JSON data are synced to the mediatheque's pivot under the `json` handle. 1.x wrote those rows without a handle, and the first sync leaves them: `folklore:upgrade` removes them, or they stay as usages of their media.
