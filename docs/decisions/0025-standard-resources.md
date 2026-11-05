# 0025: Standard resources of the Panneau plugin, which sites extend

- Status: accepted
- Date: 2026-11-05
- Issue: [#173](https://github.com/folkloreinc/laravel-folklore/issues/173)

## Context

Almost every site built on this package has the same three resources in its admin: pages with blocks, users and a media library. On a survey of 11 sites, 10 have pages with blocks, 10 have users and 8 have a media library. Each one rewrites them by hand: their fields, lists, labels, block types and policies. With Panneau 5, each one also repeats the same migration: the blocks in the page's JSON ([0023](0023-blocks-in-the-page.md)), the publication at the bottom of the form, the parent, the media library on `MediathequeResource`, the sorting of lists, and the locales of translatable values. The panneau module gives the fields under these resources ([0022](0022-page-fields.md)), not the resources.

## Decision

### Resources that sites extend

The panneau module gives `Folklore\Panneau\Resources\Pages`, `Users` and `Medias`. A site always has its own classes, which extend them, even empty, and registers them with its other resources:

```php
namespace App\Panneau\Resources;

class Pages extends \Folklore\Panneau\Resources\Pages {}
```

Nothing is activated by config: the site's class is the only entry point, and it already has a place to grow. Their ids come from the class name (`pages`, `users`, `medias`), as for any resource.

- **Pages**: title (required), slug (`PageSlug`, editable), description (optional: a site that requires it overrides `descriptionField()`), parent (`Page`) and blocks (`Blocks`), localized in `panneau.locales`, and `published`, a switch in the publication at the bottom of the form (`Layout\Publication`). The list sorts by title and publication, and searches the titles. Saving keeps the locales that the admin doesn't edit, in the page's data and in its blocks, matched by their id (`Support\OtherLocales`, on the schema of the page's data): a site edited in French whose pages have English values keeps them.
- **Users**: name, email (unique), password (hashed by the users repository, never sent back; an empty one keeps the current password) and role. The list sorts by name and email.
- **Medias**: a `MediathequeResource` of images, videos and documents (`mediaTypes()`), in a gallery and a table with thumbnails. `laravel-panneau` 5.0.0-alpha.14 gives the largest size of the types to the file, and skips a media in use on delete.

The repositories gain what the lists need, additively: the pages repository searches the titles in every locale of the site (`SearchesEntities`) and sorts `title` in the current locale, and the base repository maps a name of the `order` param to a column (`$orderColumns`, `['title' => 'data->title->{locale}']`), for values of the JSON data.

### Overriding without copying

Each field of a standard resource comes from its own method (`titleField()`, `blocksField()`, `passwordField()`…), and `fields()` lists them. A site overrides one method to change a field, and `fields()` to add or remove some with the helpers of `Resources\Concerns\EditsFields`: `insertAfter()`, `insertBefore()`, `replaceField()`, `withoutFields()`, and `localized()` to read a localized value from an entity. The form of the pages follows the order of `fields()`, with the fields of `publicationFields()` at the bottom, so that an added field lands where it is inserted. `layout()` and `list()` stay overridable as in any resource.

### Standard block types

Survey of the block types of the sites in `~/Code` on 2026-11-05: 16 Laravel applications, 14 with blocks.

| Type | Sites | Fields most sites give it |
|---|---|---|
| text | 13 | a title and a rich body (9), or a body alone (4) |
| image | 13 | the image and credits (11), and a rich caption (8); a link on some |
| heading | 8 | a title, and a size or a level that differs on each site |
| gallery | 8 | the images, and a layout that differs on each site |
| button or link | 8 | a label and a URL, and whether it leads out (3) |
| quote | 6 | a rich body and its author |
| embed | 5 | the URL of the embed, and display options that differ on each site |
| video | 4 | a media (2) or an embed (2) |
| Others | 1 to 8 each | listings of the site's content (8), stacks and groups (5), logos (5), teams (4), accordions, cards, stats, timelines (3 each)… |

The standard types are those that at least half of the sites share, and quotes and embeds, whose fields are the same everywhere: **text**, **heading**, **image**, **gallery**, **quote**, **button** and **embed**, in `Folklore\Entities\Blocks`. Each extends `Folklore\Entities\Block`, with a closed schema (`additional(false)`), accessors (`title($locale)`, `image()`, `images()`…), and its fields in the admin (`DeclaresBlockFields`: `blockLabel()`, `blockIcon()`, `blockFields()`). Their fields are those most sites give them; what differs from site to site (a size, a layout) is left to the site's subclass. Video isn't one: half the sites that have it use an embed, which the embed type covers. Listings, layouts and the other types are the sites' own.

A site declares the types it uses in `site.pages.blocks.types`, with its own classes that extend them, and adds its types there. The `BlockTypes` registry of the panneau module offers the types whose class declares its fields, first and in that order, so a type is declared once for the data and the admin; a type registered with `BlockTypes::register()` under the same id replaces it. The types of a site that don't declare their fields are registered as before.

### Default roles

`Roles\DefaultRoles::ROLES` gives two roles to the roles module of `laravel-panneau` ([its decision 0014](https://github.com/folkloreinc/laravel-panneau/blob/v5.x/docs/decisions/0014-roles-module.md)):

| Role | Permissions |
|---|---|
| `admin` | `*`: every resource, and assigning roles |
| `editor` | `pages.*` and `medias.*`: the pages and the media library, not the users |

`Roles\UserRoleStore` keeps them in the `role` column that the package's users have had since 1.x: a user has one role and no grants, and `guest`, the column's default, is no role. `Policies\PagePolicy`, `UserPolicy` and `MediaPolicy` are the `ResourcePolicy`s of the three resources. The `Role` field offers the roles of the module, or these when the module is off, with their labels translated by the package, and only users who can assign roles change it. A site turns the module on in its `config/panneau.php`, adds its own resources to the editor's permissions, and registers the policies:

```php
'roles' => [
    'enabled' => true,
    'store' => \Folklore\Panneau\Roles\UserRoleStore::class,
    'roles' => \Folklore\Panneau\Roles\DefaultRoles::ROLES,
],
```

### Labels

The labels of the resources, fields, block types and roles are translated by the package, in French and English (`folklore::panneau.*`, loaded by the panneau module).

## Consequences

- A new site has its admin with three empty classes; an existing site makes its resources extend the package's and removes what the package does. The install command that writes these classes comes next (#173).
- A fix of these resources reaches every site with an upgrade of the package.
- The standard resources need `laravel-panneau` 5.0.0-alpha.14 or later (`Publication`, the roles module, media in use); the rest of the panneau module still runs on 5.0.0-alpha.5.
- The crop of an image of a block (its `transform`) isn't kept: `Json::media()` stores the reference to the media only. Until a reference can keep it, a site that needs crops in blocks gives its image block another schema.
- Sorting by title follows the locale of the request, which `laravel-locale`'s middleware sets from the user's browser in the admin.
