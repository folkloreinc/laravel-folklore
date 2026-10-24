# 0014: Names of the JSON data and query context API

- Status: accepted, amended by [0021](0021-json-data-package.md) (the JSON data names move to `Folklore\JsonData\`)
- Date: 2026-10-22

## Context

The 2.0 design in [#56](https://github.com/folkloreinc/laravel-folklore/issues/56#issuecomment-6344885526) and the planning docs ([#67](https://github.com/folkloreinc/laravel-folklore/pull/67)) used proposed names for the classes and methods that 1.x doesn't have. They are settled before the contracts are written, so that the contracts, the docs and the pilots use the same ones.

## Decision

The proposed names are kept:

| Name | What |
|---|---|
| `Folklore\Eloquent\JsonData` | The registry of reference keys and the entry point: `map()`, `enforceMap()`, `load()`, `references()`, `parse()`, `reference()` |
| `Folklore\Eloquent\JsonReference` | A key's target: `JsonReference::to(Model::class)->by('column')->using(Handler::class)` |
| `Folklore\Eloquent\JsonReferenceEntry` | A parsed reference: column, path, key, identifier, relation |
| `Folklore\Contracts\Eloquent\ReferenceHandler` | The contract of handlers: `prepare()`, `resolveMany()`, `allows()` |
| `Folklore\Eloquent\ReferenceContext` | What a handler is given about the owner, the column, the path and the key |
| `InvalidJsonReference`, `ReferenceNotAllowed` | Exceptions |
| `MediaReferences` | The media handler, in the medias module |
| `withJsonReferences()` | Repositories: load the references of each result |
| `folklore:json-references:rewrite` | The rewrite command |
| `QueryContextResolver` | The contract of the default query context resolver |
| `ModuleRequirementMissing` | The exception of a module whose dependencies aren't installed |

Not settled here, because they had no proposed name: the typed query, the typed base entity, and the namespaces of the modules. They are named with the contracts, in phase 0.

## Consequences

- The docs use these names as final.
- Renaming one of them before 2.0.0 is a new decision, listed in the CHANGELOG as a breaking change between alphas.
