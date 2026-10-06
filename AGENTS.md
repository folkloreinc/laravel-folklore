# AGENTS.md

Guidance for AI coding agents and human contributors working on this repository.

## Language: English only

`folklore/laravel-folklore` is a public package. Everything in this repository must be written in English, even when the request or the discussion happens in another language (French, for instance):

- code: identifiers, comments, docblocks, exception, log and console messages
- commit messages
- pull requests: titles, descriptions, review comments and replies
- issues and issue comments
- documentation: README, CHANGELOG, UPGRADE guide, this file

User-facing text belongs in translation keys (`trans('...')`), never hardcoded in another language.

## What this package is

The shared foundation of the Laravel sites built by Folklore. It is a toolbox:

- **Core**, used by every site: entities and repositories (`src/Folklore/Contracts`, `src/Folklore/Entities`, `src/Folklore/Repositories`), `JsonDataCast` (`src/Folklore/Eloquent`), pages and blocks, medias (built on `folklore/laravel-mediatheque`), users and the `repository` auth provider.
- **Optional modules**, available "just in case": Customer.io, Google (Maps, Places, Drive), the PubNub broadcaster, PubSubHubbub, organisations, Panneau fields and resources, view composers, `make:*` generators.

## Working rules

- Existing sites depend on this package. Avoid breaking changes: prefer additive, opt-in changes whose defaults keep the current behaviour, and deprecate before removing.
- A module a site doesn't use must cost it nothing: no side effects at boot, no heavy mandatory dependency.
- Add or update tests with every change.
- The roadmap is tracked in [#1](https://github.com/folkloreinc/laravel-folklore/issues/1). Create a sub-issue for an item when you start working on it, and reference it in the PR.

## Development

- Requirements: PHP `^8.2`, Laravel 11 to 13 (see `composer.json`).
- Install dependencies: `composer install`
- Run tests: `vendor/bin/phpunit --testsuite Feature` (the `Unit` suite declared in `phpunit.xml` has no directory yet).
- Code style: Prettier with `@prettier/plugin-php` (`.prettierrc.json`: 4 spaces, single quotes, 100 columns).
- Layout: `src/Folklore` (namespace `Folklore\`), `src/migrations`, `src/stubs`, `tests`.
- Active development happens on the `v1.1` branch.
