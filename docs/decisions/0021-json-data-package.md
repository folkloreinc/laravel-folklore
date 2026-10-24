# 0021: JSON data moves to its own package

- Status: accepted
- Date: 2026-10-23
- Amends: [0002](0002-core-and-modules.md) (JSON data leaves the core) and [0014](0014-names.md) (the namespaces of the JSON data API)

## Context

[0002](0002-core-and-modules.md) kept 2.0 as one package and rejected splitting the modules into separate packages: sites use them together, and one package with one version is simpler to upgrade than six. That still holds for the modules. JSON data has become a different kind of code since:

- **It is most of the work.** Alphas 1 to 3 are mostly about `JsonDataCast`, and so are the open items: [#84](https://github.com/folkloreinc/laravel-folklore/issues/84), [#86](https://github.com/folkloreinc/laravel-folklore/issues/86), [#105](https://github.com/folkloreinc/laravel-folklore/issues/105).
- **It is about to double.** The JSON code is about 2,000 lines, 1,200 of them in `JsonDataCast`. The schema of [0020](0020-json-schema.md) adds nodes, filling, Laravel rules, a JSON Schema export and locales: more than the rest of the core, and a public API to keep stable until 3.0.
- **It doesn't move at the pace of 2.0.** The pilot made the cast JSON the source of truth for every structure it edits (2026-10-23), so it needs a stable `JsonDataCast` early. 2.0.0 waits for the Panneau plugin, the pages and auth modules, and a site upgraded during the beta.
- **It has users that need neither entities nor repositories.** The JSON Schema export is for API specs and front-end types, the reference format for JavaScript readers ([0005](0005-json-reference-keys.md)), and later for Panneau fields.
- **It barely depends on the rest of the package:**
  - the cast and the default handler know entities only to unwrap them into a model or an id (`JsonDataCast::isModelOrEntity()`, `ModelReferences::prepare()`);
  - the cast syncs into the mediatheque's usage graph under the `json` handle, by detecting `HasMedias` by name;
  - the rewrite command adds the pages models when the pages module is on;
  - the path helpers of `Support\Data` are 79 lines.

  The base repository's filling, the one thing only it can do with JSON data, moves to `JsonData::fill()` with [0020](0020-json-schema.md) ([#104](https://github.com/folkloreinc/laravel-folklore/issues/104)).

Keeping the code in this package under its own namespace, with a boundary checked in CI, was considered. It costs less now, but a 1.0 of JSON data would still wait for 2.0.0, and the other users would still pull the whole package.

## Decision

**JSON data becomes its own package**, `folklore/laravel-json-data`: namespace `Folklore\JsonData\`, repository `folkloreinc/laravel-json-data`, public, in English.

- **It requires only `illuminate/*`**, with the same supported versions as this package ([0001](0001-supported-versions.md)). It knows nothing of entities, repositories, the mediatheque or the modules.
- **The schema is the package's only format.** A model declares its JSON columns with `jsonDataSchema()` ([0020](0020-json-schema.md)). The package's cast compiles a schema into declarations: reference paths, with their relation, key and options, and extracted columns. For a model without a schema, it asks a declarations hook, which takes the same declarations.
- **What moves:**
  - `JsonDataCast` (without the macros), `JsonData`, `JsonReference`, `JsonReferenceEntry`, `ReferenceContext`, `ReferenceOperation`, `ModelReferences`, `InvalidJsonReference`, `ReferenceNotAllowed`;
  - the contract `ReferenceHandler`;
  - `folklore:json-references:rewrite` (its name doesn't change), and the commands to come (#86);
  - the path helpers, as an internal class;
  - the spec ([json-data.md](../json-data.md)), the decisions about JSON data (0005 to 0007, 0013, 0016 and 0020), and the cast's tests, on fixtures that declare their columns through the hook, on SQLite, MySQL and PostgreSQL (`jsonb`).
- **What stays here,** on top of the package:
  - the 1.x declarations: `HasJsonDataRelations`, `HasJsonDataColumnExtract` and the macros (`JsonDataCast::macro()`), read by an adapter that this package registers on the declarations hook. They are deprecated by [0020](0020-json-schema.md) and removed in 3.0;
  - the 1.1.8 characterization tests and the other JSON tests, as the compatibility suite of the adapter;
  - the base repository's JSON columns (`$jsonAttributes`), `withJsonReferences()`, and the 1.x filling (`$jsonAttributeFillable`, `fillModelJsonAttributes()`), deprecated by [0020](0020-json-schema.md);
  - the medias module's `MediaReferences` and its `media` and `medias` keys ([0008](0008-media-references.md));
  - the pages and blocks models, and `Support\Data` for the rest of the package.
- **The package gets hooks for what it no longer knows.** This package registers them:
  - declarations, for the models without a schema: the 1.x declarations and macros;
  - an unwrapper, so that an entity given as a value becomes its model or its id, as today;
  - a syncer per kind of relation, so that the medias module syncs into the mediatheque's usage graph under the `json` handle;
  - the models the rewrite command looks at, besides `app/Models`.

  Their names are settled with the extraction.
- **This package requires it** in its core, as `^1.0@alpha` during the alphas and `^1.0` from 2.0.0. A site that installs this package gets JSON data as it does today.
- **The 1.x names stay.** `Folklore\Eloquent\JsonDataCast` becomes a subclass of the package's cast that adds the macros, so that `JsonDataCast::macro()` keeps working. The other moved classes and `ReferenceHandler` are `class_alias`es of the package's classes: `instanceof`, `catch` and the static registry see the same class under both names. The aliases are deprecated and removed in 3.0, and Rector rules rename the uses ([0003](0003-evolve-in-place.md)).
- **Versions:** `1.0.0-alpha.N`, then beta, RC and 1.0.0, with the same release process as this package: tags from a green CI, a changelog, pre-releases until 1.0.0. The package can reach 1.0.0 before this package reaches 2.0.0.
- **When:** before the code of [0020](0020-json-schema.md)'s first slice, so that the schema is written in the package. The repository was created on 2026-10-23; the pilot stays on its prototype until after its demo of 2026-10-27.
  1. Create the package from this repository's history of the moved files, with its CI and docs.
  2. Release `1.0.0-alpha.1`, with the behaviour of this package's last alpha and no schema: until its alpha 2, which ships the schema's first slice, the package is used through this package's adapter.
  3. In the next alpha of this package, require it, remove the moved code, and add the aliases and hooks.
  4. Transfer the JSON data issues (#84, #86, #105) to the package.

## Consequences

- Amends [0002](0002-core-and-modules.md): the core is entities, repositories and the global query context, and it requires `folklore/laravel-json-data` besides `illuminate/*`. That package depends on no other Folklore package, so the core still pulls none of the 1.x line. The modules stay in this package.
- Amends [0014](0014-names.md): the names it settles stay, in the `Folklore\JsonData\` namespace. The `Folklore\Eloquent\` names of the moved classes and `Folklore\Contracts\Eloquent\ReferenceHandler` become aliases, and `Folklore\Eloquent\JsonDataCast` a subclass. `HasJsonDataRelations` and `HasJsonDataColumnExtract` don't move.
- Sites and the pilot change nothing: the 1.x names and declarations, the stored format, the registry and the command keep working. A site moves to the package's names and to `jsonDataSchema()` at its own pace, or with Rector. A new project, such as the pilot, declares its columns with the schema from the package's alpha 2.
- The package doesn't carry the 1.x API. Its cast still takes the 1.x options in a declaration (`lazy`, `sync`, `delete`, `skip`, `get`, `set`, `before_set`), which the adapter passes, until its 2.0.
- A change that touches both the cast and the base repository takes two pull requests and two releases. While [0020](0020-json-schema.md) is being built, this package requires the package's alphas.
- The decisions and the spec that move are copied into the package with their numbers and a link back. Here, they stay as they are, as the history of 2.0.
- Panneau 5, front-end tooling or another Laravel project can use JSON data without this package.
- One more repository to maintain: its CI matrix, its releases, its Packagist entry, and its line in the workspace inventory.

## Open questions

- Whether `MediaReferences` later moves to the mediatheque, as an integration it ships when the package is installed, so that a project gets media references without this package's medias module ([roadmap.md](../roadmap.md#open-questions)).

## Since then

Settled with the extraction, in 2.0.0-alpha.4 and laravel-json-data's 1.0.0-alpha.1 and alpha.2:

- The names of the hooks are `JsonData::declareUsing()`, `unwrapUsing()`, `syncUsing()` and `JsonReferencesRewriteCommand::discoverUsing()`.
- The rewrite command is `json-data:rewrite-references`, under the package's name ([laravel-json-data's decision 0022](https://github.com/folkloreinc/laravel-json-data/blob/v1.x/docs/decisions/0022-command-names.md)), not `folklore:json-references:rewrite`.
- The decisions copied into the package are 0005 to 0007, 0013 and 0016. [0020](0020-json-schema.md) and this decision stay here, and the package links to them.
- This package requires `^1.0.0-alpha.2`. A site requires the package too until it has a stable release, since a stability flag doesn't apply to the dependencies of a required package.
- The work isn't tied to the pilot's demo: the pilot moves to the package with this package's 2.0.0-alpha.4.
