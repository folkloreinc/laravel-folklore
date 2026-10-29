# Architecture of 2.0

Design of `laravel-folklore` 2.0. Each choice is recorded in [decisions/](decisions/); the 1.x it starts from is in [current-state.md](current-state.md). New class names are settled in [decisions/0014](decisions/0014-names.md); the typed query, the typed base entity and the module namespaces are named with the contracts ([roadmap.md](roadmap.md)). Details marked **Proposed** go beyond the decisions and are open to review.

## Goals

- **The shared foundation of the Folklore Laravel sites**, built on `laravel-mediatheque` 2, `laravel-image` 2 and Panneau 5.
- **A core with no dependency on the other Folklore packages**, that a new project can adopt alone: entities, repositories, the global query context and JSON columns. The JSON columns live in their own package, `folklore/laravel-json-data`, which the core requires and which depends on no other Folklore package ([decisions/0021](decisions/0021-json-data-package.md)).
- **Opt-in modules** in the same package, that cost nothing when they are off.
- **Cheap upgrades:** the sites on 1.1 upgrade without rewriting their repositories and entities. The 1.x API is kept, deprecated, until 3.0.
- **Typed new code**, checked by Larastan at its maximum level, next to the 1.x signatures that sites override.

## Boundaries

| Concern | Owner |
|---|---|
| Entities, repositories, query context | This package, core |
| JSON references, and the schema of JSON columns | `folklore/laravel-json-data`, required by the core ([decisions/0021](decisions/0021-json-data-package.md)). This package adds the entities, the repositories' JSON columns and media references |
| Media storage, ingest, renditions, `media://` references, usage graph | `laravel-mediatheque` 2. This package's medias module adapts it to entities and JSON data |
| Image presets and URLs | `laravel-image` 2, through the medias module |
| Admin UI and its protocol | Panneau 5. This package ships a plugin: a repository adapter and page fields |
| Locales and localized routes | `laravel-locale`, used by the pages module |
| Tenancy, users' roles, policies | The application. The package passes a query context and calls handlers the application configures |

## Overview

```text
folklore/laravel-folklore
├── Core (illuminate/* and folklore/laravel-json-data)
│   ├── Contracts: Entity, HasModel, ToEntity, Entities (repository)
│   ├── Entities: optional typed base entity
│   ├── Repositories: Entities base class, typed query, global query context
│   └── Eloquent: the 1.x JSON API on laravel-json-data (cast subclass with macros, 1.x declarations, aliases)
└── Modules (opt-in, in config/site.php)
    ├── medias      → laravel-mediatheque 2, laravel-image 2
    ├── pages       → laravel-locale, eloquent-sluggable
    ├── auth        → laravel/fortify
    ├── panneau     → laravel-panneau 5
    └── services    → Customer.io, Google, PubNub, response macros, composers, commands
```

## Configuration and modules

Modules are turned on in `config/site.php`, which 1.x already uses (`site.medias.download`). The package merges its defaults (`config/site.php` in the package), where every module is off:

```php
'modules' => [
    'medias' => false,        // Medias repository, mediatheque model bindings
    'pages' => false,         // Pages and blocks repositories
    'auth' => false,          // `repository` auth provider, users repository, Fortify callbacks, `users:create`
    'panneau' => false,       // Panneau 5 plugin: the adapter of the repositories
    'organisations' => false, // Organisations repository (removed in 2.0)
    'sites' => false,         // Site manager (removed in 2.0)
    'http' => false,          // `routeForReactRouter`, `pixel` and `csv` responses, OffsetPaginator's page resolver
    'assets' => false,        // `assets:view`, and LocalMiddleware in the local environment (deprecated)
    'customerio' => false,    // Customer.io client and mail transport, when `services.customerio` is set
    'google' => false,        // Google Maps, Places and Drive
    'pubnub' => false,        // `pubnub` broadcast driver
    'pubsubhubbub' => false,  // PubSubHubbub client and commands, when `pubsubhubbub` is set (removed in 2.0)
],
```

- With every module off, the package registers the core and nothing else: the repositories of `config('app.repositories')`, `daemon:restart`, and, in the local environment, the `make:*` generators and the publishing of the migrations. No binding of its own repositories, macro, middleware, auth provider, broadcast driver or global override ([decisions/0002](decisions/0002-core-and-modules.md)).
- A module that is on checks, when the package registers, that the packages it needs are installed (`ServiceProvider::MODULES`), and throws `Exceptions\ModuleRequirementMissing` with the package to require:

  | Module | Needs |
  |---|---|
  | `medias` | `folklore/laravel-mediatheque` 2, `folklore/laravel-image` 2 |
  | `pages` | `folklore/laravel-mediatheque` 2 (its models use `HasMedias`), `cviebrock/eloquent-sluggable`, `folklore/laravel-locale` |
  | `auth` | `laravel/fortify` |
  | `panneau` | `folklore/laravel-panneau` 5 (5.0.0-alpha.4 or later) |
  | `pubnub` | `pubnub/pubnub` |

  Google Places still needs `google/apiclient` when it is used, until it moves to Laravel's HTTP client.
- The modules use parts of Laravel that the core doesn't require (HTTP, routing, auth, mail, broadcasting, views, and `Foundation` for the auth module's `User` model): a Laravel application has them.
- For now, the package's service provider registers each module's part. A module gets its own service provider when it gets its namespace, with its alpha.
- The panneau module, the Panneau 5 plugin, has its namespace and its service provider (`Panneau\PanneauServiceProvider`), which the package's registers when the module is on. It has the repository adapter and the page fields, whose `PageSlug` and `Page` fields need the pages module. The Panneau 1.x fields and resources are removed.
- `folklore:upgrade` writes the modules an upgraded site uses into its `config/site.php` ([upgrade.md](upgrade.md#what-folkloreupgrade-does)).

## Entities

- **Contracts keep their 1.x names and methods.** `Entity` (`id(): string`), `HasModel` and `ToEntity` stay, but `Entity` no longer extends Panneau's `ResourceItem` ([decisions/0003](decisions/0003-evolve-in-place.md)).
- **An optional typed base entity** for new code, `Entities\ModelEntity`: built from a model, which it keeps in a readonly property, with `id()` as the model's entity key ([decisions/0015](decisions/0015-entity-key.md)) and `getModel()` for the edges that need a model (Fortify, Sanctum). It is the pilot's prototype, under the same name; the prototype's `id()` read the route key. The 1.x entities don't extend it, so their protected properties (`$model`, `$data`, `$metadata`, `$sizes`, `$files`) keep working.
- `HasEntityKey`, on models, says which column holds the entity's public id, for the models whose public id isn't their key ([decisions/0015](decisions/0015-entity-key.md)).
- `HasModel`, `ToEntity` and the `Entities` repository contract are generic (`HasModel<TModel>`, `ToEntity<TEntity>`, `Entities<TEntity>`), with defaults, so 1.x classes that don't say their types keep passing Larastan.
- `HasTypedEntity`, `to_entity()`, `to_id()` and `SerializesEntityModel` stay, with the same behaviour and payload.
- The `make:entity` and `make:repository` generators produce typed classes on the 2.0 bases.

### Review against the pilot's prototype

The pilot writes its entities and repositories on a prototype of the core, to be replaced by this package ([#56](https://github.com/folkloreinc/laravel-folklore/issues/56)). Reviewed in phase 0:

| Prototype | 2.0 | Swap |
|---|---|---|
| `Contracts\Entities\Entity` (`id(): string`) | Same | Ready |
| `HasModel<TModel>`, `ToEntity<TEntity>` (`toEntity(): Entity`) | Same, generic; `toEntity()` returns `?Entity`, which a model may narrow | Ready |
| `Contracts\Repositories\Entities<TEntity>` with `findById()` only | The 1.x contract, generic: also `get()`, `count()`, `create()`… | Ready for repositories whose methods match its signatures: they extend the base class, which implements them. A repository that declares `create(array $data)` or `update(Model $model, array $data)` doesn't load, since PHP refuses a narrower parameter type: it renames those methods, or keeps the contract's signatures and types them in PHPDoc. The contract stays as it is ([#83](https://github.com/folkloreinc/laravel-folklore/issues/83), closed) |
| `Entities\ModelEntity<TModel>`, `id()` = route key | Same, `id()` = the model's entity key (`HasEntityKey`), or its key | Ready once the pilot's models implement `HasEntityKey` |
| `Repositories\Entities<TModel, TEntity>`: `newModel()`, `newQuery(): Builder`, `toEntity()` | The 1.x base class, not generic; `newQuery()` untyped, entities built by `getEntityFromModel()` | 2.0.0-alpha.1: the base class gets `@template TModel, TEntity` with defaults |
| `findById()` by the model's route key (the public uid) | `findById()` by the model's entity key, or its key ([decisions/0015](decisions/0015-entity-key.md)) | 2.0.0-alpha.1, in the base class; the pilot implements `HasEntityKey` in the trait that gives its models their uid |

### Review against `laravel-mediatheque` 2

- `MediaReference` (`media://` by uid, `medias://` by id, with `SCHEME` and `LEGACY_SCHEME`) fits `MediaReferences`: it parses the values it prepares. `MediaReferences::resolveMany()` resolves the identifiers of its key in one query on the key's column, with the media's files, as `Mediatheque::resolveMany()` does, but without the facade, so that it works under `Mediatheque::fake()` ([#98](https://github.com/folkloreinc/laravel-folklore/issues/98)).
- `JsonData::parse()` is more lenient than `MediaReference::tryParse()`, which checks the uid's format. The handler checks it; the registry only checks the key.
- The usage graph (`HasMedias`, `usages()`) shipped in the mediatheque's 2.0.0-alpha.6. JSON references sync into it with `syncMedias()`, under one handle, `json`, for every JSON column of a model ([json-data.md](json-data.md#syncing-media-references)). Settled in 2.0.0-alpha.1.

## Repositories

`Repositories\Entities` stays the base class, with its protected API ([current-state.md](current-state.md#repositories)).

- The `Entities` contract no longer extends Panneau's `Repository`.
- **A typed query** is added next to `get($params, $page, $count)`: `queryEntities(EntitiesQuery $query): LengthAwarePaginator` (not `query()`, which 1.x repositories may already have), with `withGlobalQuery()`, in the `QueriesEntities` contract. `Repositories\Query\EntitiesQuery` is close to Panneau 5's `ResourceQuery`: filters (the 1.x params), search, sorts (`Sort`, `SortDirection`), `ids`, `except`, page and page size. It maps to the same `buildQueryFromParams()`, so a site's overrides apply to both. The Panneau plugin's adapter is built on it. The base class implements it. The `Entities` contract doesn't extend it, so that a site class that implements `Entities` without the base class keeps loading.
- Existing methods get PHPDoc types, checked by Larastan; their signatures don't change.
- Repositories list their JSON columns (`$jsonAttributes`), not only `data`, and can load the references of a whole result (`withJsonReferences()`) ([json-data.md](json-data.md#repositories)).
- `findById()`, `update()`, `destroy()` and `valueById()` apply the query context, as `get()` does, but not the default params ([decisions/0017](decisions/0017-finds-apply-the-query-context.md)).
- Repositories find by the model's entity key (`HasEntityKey::getEntityKeyName()`), or by its key name when the model doesn't implement the contract ([decisions/0015](decisions/0015-entity-key.md)).
- A repository bound by the package or by `RepositoriesServiceProvider` stays a `bind()`, not a singleton.

### Global query context

([decisions/0004](decisions/0004-global-query-context.md))

```php
$games->setGlobalQuery(['contest' => $contest->id]);          // 1.x: changes this instance
$scoped = $games->withGlobalQuery(['contest' => $contest->id]); // a copy; $games is unchanged

// A default context, applied to every repository without an explicit one
// QueryContextResolver::resolve(Entities $repository): array
$this->app->bind(QueryContextResolver::class, CurrentTenantContext::class);  // resolved for each query
```

The context is merged into the params: for lists, defaults, then the resolver's context, then the global query, then the call's params; for finds, the same without the defaults. `withGlobalQuery()` merges into the repository's global query. Panneau 5's `scope()` maps to `withGlobalQuery()` in the plugin.

## JSON data

`JsonDataCast` keeps its 1.x declaration (`getJsonDataRelations()`) and stored format (`{key}://{identifier}`). 2.0 adds a global registry of reference keys, handlers per key, batch resolution, memoization, a reference listing, a recursive wildcard, a rewrite command and a schema of JSON columns ([decisions 0005 to 0008](decisions/), [0020](decisions/0020-json-schema.md)).

It lives in `folklore/laravel-json-data` since 2.0.0-alpha.4 ([decisions/0021](decisions/0021-json-data-package.md)), which specifies it; [json-data.md](json-data.md) covers what this package adds. The package declares columns only with `jsonDataSchema()`. The 1.x names stay until 3.0: aliases, and a subclass of the cast for the macros. This package keeps the 1.x declarations (`HasJsonDataRelations`, `HasJsonDataColumnExtract`, the macros), the repositories' JSON columns, the media references, and the hooks it registers in the package: the 1.x declarations, entities unwrapped into models, the medias module's sync into the usage graph, and the pages models for the rewrite command.

## Medias module

On `laravel-mediatheque` 2 and `laravel-image` 2 ([decisions/0008](decisions/0008-media-references.md)).

- **Models:** `Models\Media` and `Models\MediaFile` extend the mediatheque's. The module sets them in `config('mediatheque.models')` when the site keeps the mediatheque's defaults; a site with its own models sets them there. The package no longer rebinds the mediatheque's model contracts.
- **Entities:** `Entities\Media`, `Image`, `MediaFile` and the metadata entities read the mediatheque's contracts (`original()`, `files()`, `metadata()`), and keep their 1.x contracts and protected properties. `id()` stays the internal id, which `medias://` references and the 1.x JSON shape use; `uid()` gives the public one.
- **Image sizes** are `laravel-image` 2 presets (`config('image.presets')`): one `ImageSize` per preset, with the variant's URL, dimensions and type (`Image::for($media)->preset(...)->variant()`). An image that `laravel-image` can't render (no dimensions, an unknown preset) falls back to its original's URL. `setFilters()` is deprecated, and only understands a preset name, `width`, `height`, `fit`, `format` and `quality`.
- **Creating media:** `createFromFile` goes through `Mediatheque::ingest()`, `updateFromFile` through `replaceOriginal()`. Downloading from a URL, with its host and size limits, stays here until `laravel-mediatheque` imports URLs behind its SSRF guard.
- **JSON references** sync into the mediatheque's `medias_pivot` under the handle `json` ([json-data.md](json-data.md#syncing-media-references)).
- **Reference keys:** the module maps `media` (by uid) and `medias` (by id) to its media model, with `MediaReferences`, and writes the one of `site.medias.reference_key` ([json-data.md](json-data.md#media-references)).
- **JSON:** `Http\Resources\MediaResource` extends the mediatheque's canonical `MediaResource` (its 2.0.0-alpha.10), from an entity or a model. Until 3.0, it gives by default the shape of 1.1 that front ends read (`site.medias.resource` set to `legacy`): the internal `id`, the original's `url`, the entity's `metadata`, an image's `sizes` from the `laravel-image` presets, and `files` as a list with the 1.x handles, the original first. `site.medias.resource` set to `canonical`, or `canonical()`, gives the mediatheque's shape ([#124](https://github.com/folkloreinc/laravel-folklore/issues/124)).

## Pages module

([decisions/0009](decisions/0009-pages-and-blocks.md))

- Blocks are stored in the page's JSON; `blocks_pivot` goes away and `folklore:upgrade` converts existing data.
- The `slug_{locale}` columns stay. Route names (`home`, `page`, `page_with_parent`) are configurable, in `site.pages.routes`: `Pages\PageRoutes` gives the URLs of pages from them, for the `Page` entity and the `PageSlug` field.
- A type registry maps page and block types to entity classes, in `config/site.php` or in code. `HasTypedEntity` reads `$entitiesByType` on the model first, then the registry.

## Auth module

([decisions/0010](decisions/0010-users-and-auth.md)) The `repository` auth driver, the `User` entity with two-factor authentication, and the Fortify callbacks boot only when the module is enabled.

## Panneau 5 plugin

([decisions/0018](decisions/0018-panneau-plugin-php-only.md), which replaces [0011](decisions/0011-panneau-plugin.md)) An adapter for Panneau's `ResourceRepository` on the typed query, `scope()` on the query context, and the `Page`, `Pages`, `Blocks` and `PageSlug` fields. It ships before the end of Panneau 5's betas.

- **The repository adapter**, below, in the slice "Panneau 5 adapter and media entities" ([#121](https://github.com/folkloreinc/laravel-folklore/issues/121)).
- **The page fields**, below, in the slice "Pages" ([#122](https://github.com/folkloreinc/laravel-folklore/issues/122), [decisions/0022](decisions/0022-page-fields.md)).
- **PHP only.** The fields extend Panneau 5's built-in types: `Page` and `Pages` are relations to the pages resource, `Blocks` a repeater whose types come from the block type registry, and `PageSlug` Panneau's slug, with the URL that the pages module gives.
- **Slug features in Panneau.** A source per locale, a URL computed by the backend and checking that a slug is free are proposed to Panneau's generic `slug` field.

### Repository adapter

A Panneau 5 resource returns a repository contract from `repository()`, and the panneau module adapts the repository: it registers `Panneau\EntitiesRepository` with `Panneau::repositoryAdapter()` for every repository with the typed query (`QueriesEntities`), and `Panneau\SearchableEntitiesRepository` for those that search (`SearchesEntities`), when Panneau first needs its adapters, so a site's own adapter, registered later, takes precedence. A repository that only implements the 1.x `Entities` contract has no adapter.

```php
final class ArticlesResource extends Resource
{
    public function repository(): string
    {
        return \App\Contracts\Repositories\Articles::class; // a repository on Folklore's base class
    }

    public function itemClass(): string
    {
        return \App\Contracts\Entities\Article::class;
    }

    public function scope(Request $request): array
    {
        return ['workspace' => $request->user()->workspace_id]; // the repository's params
    }

    public function creating(array $values, Request $request): array
    {
        return [...$values, 'workspace_id' => $request->user()->workspace_id]; // the columns
    }

    public function toItem(object $item): array
    {
        return ['title' => $item->title(), 'slug' => $item->slugs()];
    }
}
```

- **Lists** go through `queryEntities()`. The list's filters are the repository's params, which its `buildQueryFromParams()` applies, as the 1.x `get()` does; a range filter is `['from' => …, 'to' => …]`. A filter that names a param of the scope or of the default resolver's context is dropped, since the params of a call take precedence over the context, and so is one that names a param the base repository reads itself (`order`, `order_direction`, `identifier`, `offset`, `offset_paginator`, `search`). Its sorts are the `order` param, a list of `[column, direction]` pairs, which the repository's orderable columns allow; `queryEntities()` then orders by the entity key, so pages are stable. Its ids and exclusions are entity keys.
- **Search is opt-in.** A repository that applies the `search` param to its own fields says so with `Contracts\Repositories\SearchesEntities`: its adapter is Panneau's `Searchable`, and gives it the list's search. The others aren't `Searchable`, so Panneau offers no search for them rather than a search that finds everything.
- **The scope** is the repository's query context: the adapter calls `withGlobalQuery($scope)` for lists, finds, creates and slug checks, on top of the default resolver's context ([decisions/0004](decisions/0004-global-query-context.md)). Its params are the repository's, which aren't always columns (a `queryColumns` alias, a flag), so the adapter doesn't write them on create, unlike Panneau's `EloquentRepository`: the resource sets the values that put the item within the scope in `creating()`. The create and the find that follows run in a transaction of the default connection (`connection()`), and an entity created outside its scope is rolled back with a `LogicException`. Updates and deletes find the entity by its id within the repository's own context: Panneau found it within the scope first, and ids are unique across scopes (a key or a uid). An update or delete of an entity that is gone is a `ModelNotFoundException`, a 404.
- **Items** are the repository's entities, and their public id is their `id()`: the model's entity key (`HasEntityKey`), or its key ([decisions/0015](decisions/0015-entity-key.md)). Entities have no public properties, so the resource serializes them with `toItem()`.
- **The version** is a hash of the attributes of the entity's model as read or last saved (`getRawOriginal()`), for an entity that implements `HasModel`, and null otherwise, which turns off Panneau's `If-Match` check. `updated_at` would keep the same version for two saves in the same second. Creates and updates find the entity again, so the version they return is the one the next update compares, whatever the database does to the stored values (`jsonb`).
- **Unique slugs.** The adapter implements Panneau's `ChecksSlugs` with the repository's `slugExists()` (`Contracts\Repositories\ChecksSlugs`), within the scope. A repository opts in with the `Repositories\Concerns\ChecksSlugs` trait, which looks the slug up in the column named after its field, or `{field}_{locale}` for a localized slug (`slug_fr`, as the pages models have them), deleted entities included, as Panneau's `EloquentRepository` does. It overrides `getSlugColumn()` to look elsewhere, or `newSlugQuery()` to narrow the lookup with the form's values (a page's parent). The trait is opt-in, so that the method doesn't clash with a repository that already has a `slugExists()`. Checking a slug on a repository without it throws a `LogicException`.
- `Testing\PanneauRepositoryContractTests` checks a repository through the adapter: it finds what it creates within the scope, not what is outside it, and the version of a saved item is the one found next.
- Not adapted yet: Panneau's `ChunkedQuery` (queued actions), `Duplicable` and `Restorable`.

### Page fields

([decisions/0022](decisions/0022-page-fields.md)) `Panneau\Fields\Page`, `Pages`, `Blocks` and `PageSlug` configure Panneau 5's built-in types. A site registers its block types once, in a service provider's `boot()`:

```php
use Folklore\Panneau\BlockTypes;
use Folklore\Panneau\Fields\Blocks;
use Folklore\Panneau\Fields\Page;
use Panneau\Fields\BlockType;

$this->app->make(BlockTypes::class)->register(fn () => [
    BlockType::make('text', __('Text'), [RichText::make('body')->localized()]),
    BlockType::make('section', __('Section'), [Page::make('section')->resource(SectionsResource::class)]),
    BlockType::make('stack', __('Stack'), [Blocks::make('blocks')->exceptTypes('stack')]),
]);
```

```php
final class PagesResource extends Resource
{
    public function repository(): string
    {
        return \Folklore\Contracts\Repositories\Pages::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->localized()->resolveUsing(fn (PageContract $page) => ['fr' => $page->title('fr'), 'en' => $page->title('en')]),
            PageSlug::make('slug'),           // localized, from the title, with the page's URL
            Page::make('parent'),             // a relation to the `pages` resource
            Blocks::make('blocks')->maxDepth(2),
        ];
    }
}

final class SectionsResource extends PagesResource // the pages a field offers: the sections
{
    public function scope(Request $request): array
    {
        return ['type' => ['section']];
    }
}
```

- **`Page` and `Pages`** are relations to the `pages` resource (`Page::RESOURCE`), multiple for `Pages`. To offer some types of pages, they relate to a pages resource whose `scope()` has these types: its picker lists them, and Panneau refuses the others when saving. The pages repository reads the `type` param. Their values are the pages' ids; models are turned into their entities, which the related resource's repository gives ids to.
- **`Blocks`** is a `repeater` of typed blocks. Its types are the `BlockTypes` registry's (instances, or closures called in the request, so that labels are translated), or those given to `types()`, narrowed by id with `onlyTypes()` and `exceptTypes()`. A type with a `Blocks` field nests blocks: `maxDepth()` counts levels from the field that sets it, as in 1.x (`maxDepth(1)` nests none), the smallest limit of the nesting applies, 3 levels from the outermost field when no field sets one, and a type whose blocks would be deeper isn't offered. The blocks of an entity are read from its `blocks()` (`HasBlocks`, or any entity with the method) for a field named `blocks`, and from block entities, models or arrays.
- **Block ids.** Each block has its `id`. Saved, a block keeps it only if it is one of the item's blocks, at any level, and no other block of the save kept it, so that the pages repository, which stores blocks as rows linked through `blocks_pivot`, updates them rather than creating new rows, and never updates another page's. The id isn't written into the block's data.
- **Versions.** The adapter's version of an entity with blocks (`HasBlocks`) hashes the attributes of its blocks too, at every level: a stale form that only changed a block gets a `412`.
- **Relations inside blocks** are turned into references (`{ "id", "label" }`), from the related items, or from the ids that blocks stored, found within the related resource's scope (one query per related resource for a list of blocks), as Panneau does for the relations at the top of an item ([folkloreinc/laravel-panneau#117](https://github.com/folkloreinc/laravel-panneau/issues/117)). The label is the related resource's `itemTitle` field, read through the field: a resource on entities gives that field a `resolveUsing()`.
- **`PageSlug`** is a `slug`, localized and from the `title` by default, read from the item's `slug($locale)` in Panneau's locales. Its check gives the page's URL from `Pages\PageRoutes`: `page`, or `page_with_parent` with the slug of the page of the form's `parent` field (`parentField()`, null for pages without a parent), and the prefix in front of the slug. The parent is found through the pages repository, not within the resource's scope: it only gives the URL. It is disabled for the home page (its `handle` is `home`). `url()` replaces the URL, for other routes.
- **Unique slugs.** `PageSlug` is unique by default (`notUnique()` turns it off). The pages repository implements `ChecksSlugs` on its `slug_{locale}` columns, across the types of pages: it leaves the `type` params of the scope out of the lookup. Another resource's repository needs the `Repositories\Concerns\ChecksSlugs` trait.
- **Not covered yet.** Media inside blocks aren't turned into media references ([folkloreinc/laravel-panneau#117](https://github.com/folkloreinc/laravel-panneau/issues/117)). A field common to every type of a field (1.x sites' `withPlacements()`) is added by a subclass:

  ```php
  final class PlacedBlocks extends Blocks
  {
      public function blockTypes(): array
      {
          return $this->types ??= array_map(fn (BlockType $type): BlockType => (clone $type)->fields([
              Select::make('placement')->options(['content' => __('Content'), 'aside' => __('Aside')])->required(),
              ...$type->getFields(),
          ]), parent::blockTypes());
      }
  }
  ```


## Other modules

([decisions/0012](decisions/0012-other-modules.md)) Customer.io, Google (Places on Laravel's HTTP client), PubNub, the view composers and `routeForReactRouter`, the `csv` and `pixel` macros. `daemon:restart` stays in the core. The site manager, organisations, PubSubHubbub and the unused contracts are removed. The global paginator resolver override is removed.

## Compatibility and deprecations

([decisions/0003](decisions/0003-evolve-in-place.md))

- What 2.0 replaces stays on the same class, marked `@deprecated`, and logs a deprecation on Laravel's `deprecations` channel when it is used.
- The 1.1 test suite runs on `v2.x` as the compatibility suite, except what covers Panneau 1.x and the removed modules.
- `folklore:upgrade --check` lists what a site uses that changed, from its code read as text and its database: modules to turn on, removed modules, packages to require, Panneau contracts, the site's own JSON references, unmapped or mismatched JSON keys, block data to convert, the media API, deprecated API ([upgrade.md](upgrade.md#what---check-reports)). `folklore:upgrade` enables the site's modules, sets the media reference key, and moves the rows of `medias_pivot` that 1.x wrote without a handle under the `json` handle; it converts block data with the pages module.
- Rector rules rename what moved and rewrite the 1.x API that code can follow (`FolkloreSetList::UPGRADE_2_0`, [upgrade.md](upgrade.md#the-rector-rules)). They are in `src/Folklore/Rector`, for the sites that require Rector: the package only requires it for its tests.
- 3.0 removes the deprecated API.

## Testing

- The core's tests run with `illuminate/*` only. Each module's tests run with its dependencies, and check that the module stays off when they are missing.
- Contract tests for the base entity and repository, that sites can run against their own classes, and for a repository through the Panneau adapter.
- CI runs the conformance kit of the Panneau protocol (`@panneau/protocol`) on a Panneau resource whose repository is a Folklore repository (`testbench.yaml`, `tests/conformance`).
- `JsonDataCast` keeps the 1.1.8 characterization tests, on SQLite, MySQL and PostgreSQL (`jsonb`).

## Package layout

PSR-4, `Folklore\` → `src/Folklore/`. The files stay where 1.x has them: PSR-0 and PSR-4 resolve every 1.x class to the same path, so the switch moves nothing, and fixes made on `v1.1` keep applying to `v2.x`. New code goes into the same tree, next to the 1.x classes it evolves.

```text
src/Folklore/
├── Contracts/            core contracts
├── Entities/             1.x entities, the typed base entity
├── Repositories/         Entities base class, typed query, context
├── Eloquent/             JsonDataCast (subclass with the macros), the 1.x declarations, MediaReferences, the aliases of the classes moved to laravel-json-data (decisions/0021)
├── Medias/               medias module (proposed)
├── Pages/                pages module: the routes of pages (the rest proposed)
├── Auth/                 auth module (proposed)
├── Panneau/              Panneau 5 plugin
├── Services/             Customer.io, Google
├── Support/              helpers, concerns
├── Upgrade/              folklore:upgrade and its --check
├── Rector/               the Rector rules of the upgrade from 1.1
└── ServiceProvider.php
```

The module namespaces are named with the contracts. 1.x classes that move into a module keep a class alias at their 1.x name until 3.0.

## Dependencies

| Package | 2.0.0 | Alphas (latest: 2.0.0-alpha.4) |
|---|---|---|
| `illuminate/console`, `contracts`, `database`, `pagination`, `support` `^12.69\|^13.30` | Required | Required |
| `folklore/laravel-json-data` ([decisions/0021](decisions/0021-json-data-package.md)) | `^1.0`, required | `^1.0.0-alpha.2`, required since 2.0.0-alpha.4. A site requires it too (`^1.0@alpha`) until it has a stable release |
| `folklore/laravel-mediatheque`, `folklore/laravel-image` | `^2.0`, suggested (medias module) | Suggested: `laravel-mediatheque ^2.0.0-alpha.10`, `laravel-image ^2.0.0-alpha.1`. `laravel-mediatheque` below 2.0.0-alpha.10 conflicts, for its canonical `MediaResource` (below alpha 7 since alpha 2) |
| `folklore/laravel-locale ^8.1`, `cviebrock/eloquent-sluggable` | Suggested (pages module) | Same |
| `laravel/fortify` | Suggested (auth module) | Same |
| `folklore/laravel-panneau` | `^5.0`, suggested (panneau module) | Suggested: `^5.0.0-alpha.4`, which has `ChecksSlugs`. Its pre-releases below 5.0.0-alpha.4 conflict; 1.x doesn't, though the Panneau 1.x fields are removed |
| `guzzlehttp/guzzle`, `parsecsv/php-parsecsv`, `pubnub/pubnub` | Suggested (services) | Same |
| `google/apiclient` | Removed | Suggested, for Places until it moves to Laravel's HTTP client |

## Supported versions

PHP 8.3+ and Laravel 12+ ([decisions/0001](decisions/0001-supported-versions.md)).
