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
- Every change comes with tests (a regression test for each bug fix) and must pass CI before it lands.
- The roadmap is tracked in [#1](https://github.com/folkloreinc/laravel-folklore/issues/1). Create a sub-issue for an item when you start working on it, and reference it in the PR.

## Development

- Requirements: PHP `^8.3`, Laravel 11 to 13 (see `composer.json`).
- Install dependencies: `composer install`
- Run tests: `composer test` (PHPUnit with Orchestra Testbench; suites in `tests/Unit` and `tests/Feature`). They use SQLite in memory by default. To run them against MySQL, set `DB_DRIVER=mysql` with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`; the database is wiped before each test.
- Format code: `composer format` (Laravel Pint, `laravel` preset, see `pint.json`).
- Static analysis: `composer analyse` (Larastan, see `phpstan.neon`). Existing errors are listed in `phpstan-baseline.neon`: never add new errors to it, fix them instead. When a change fixes baselined errors, regenerate it with `vendor/bin/phpstan analyse --generate-baseline --memory-limit=2G`. If Larastan reports unmatched baseline entries after a dependency update, refresh Testbench's package discovery (`vendor/bin/testbench package:discover`) and clear the result cache (`vendor/bin/phpstan clear-result-cache`) before regenerating.
- CI (GitHub Actions): `.github/workflows/tests.yml` runs the tests on PHP 8.3 and 8.4 × Laravel 11 to 13 with SQLite, and on PHP 8.4 × Laravel 11 to 13 with laravel-panneau 1.3 and with MySQL 8.4; `.github/workflows/code-quality.yml` runs `composer validate`, Pint and Larastan.
- The commit that applied Pint to the whole codebase is listed in `.git-blame-ignore-revs`; run `git config blame.ignoreRevsFile .git-blame-ignore-revs` to skip it in `git blame`.
- Layout: `src/Folklore` (namespace `Folklore\`), `src/migrations`, `src/stubs`, `tests`.
- Active development happens on the `v1.1` branch.
