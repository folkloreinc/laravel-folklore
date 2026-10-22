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

**Branch `v2.x`.** 2.0 is developed on `v2.x`. Read [`docs/`](docs/README.md) first: its architecture and decisions override the 1.x rules in this file wherever they differ. Until the 2.0 tooling lands (see [`docs/roadmap.md`](docs/roadmap.md), phase 0), the rest of this file describes 1.x.

## Releases

1.x is released from `v1.1`. 2.0 is released from `v2.x` in pre-releases, then 2.0.0, on Packagist:

- **Versions:** `v2.0.0-alpha.N` for each slice of the [roadmap](docs/roadmap.md), then `v2.0.0-beta.N`, `v2.0.0-rc.N` and `v2.0.0`. Until the release candidate, an alpha can break the previous one. Projects require `^2.0@alpha` (or `@beta`, `@rc`). Composer aliases `v2.x` as `2.x-dev`.
- **Changelog:** every change that users of an alpha will notice goes in the `[Unreleased]` section of `CHANGELOG.md`, in the same pull request, with breaking changes under **Breaking changes**.
- **Only from a green commit of `v2.x`:** `.github/workflows/release.yml` runs on `v2.*` tags. It refuses a tag that isn't on `v2.x`, or whose commit doesn't have the `Composer, Pint and Larastan` job and every `PHP …` job of the tests green (`.github/scripts/check-green.sh`). It publishes the GitHub release with the changelog section (`.github/scripts/release-notes.sh`), marked as a pre-release for alpha, beta and RC versions, so 1.x stays the latest release.

Steps:

1. Open a release pull request against `v2.x` that moves the `[Unreleased]` changes to a `## [2.0.0-alpha.1] - YYYY-MM-DD` section, with the **breaking changes since the previous alpha** first, and adds its link at the bottom (`[2.0.0-alpha.1]: https://github.com/folkloreinc/laravel-folklore/releases/tag/v2.0.0-alpha.1`). Check the roadmap boxes of the slice.
2. Merge it, and wait for CI to pass on the merge commit of `v2.x`.
3. Tag that commit and push the tag:

   ```bash
   git switch v2.x && git pull
   git tag -a v2.0.0-alpha.1 -m "v2.0.0-alpha.1"
   git push origin v2.0.0-alpha.1
   ```

4. The `Release` workflow checks the tag and CI, and publishes the GitHub release. Packagist picks up the tag.
5. Check that [Packagist](https://packagist.org/packages/folklore/laravel-folklore) lists the version, then tell the pilot projects.

A tag that fails the checks publishes nothing: delete it (`git push origin :refs/tags/v2.0.0-alpha.1`), fix the cause, and tag again. Never move a tag that Packagist already has.

## Development

- Requirements: PHP `^8.3`, Laravel 11 to 13 (see `composer.json`).
- Install dependencies: `composer install`
- Run tests: `composer test` (PHPUnit with Orchestra Testbench; suites in `tests/Unit` and `tests/Feature`). They use SQLite in memory by default. To run them against MySQL, set `DB_DRIVER=mysql` with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`; the database is wiped before each test.
- Format code: `composer format` (Laravel Pint, `laravel` preset, see `pint.json`).
- Static analysis: `composer analyse` (Larastan, see `phpstan.neon`). Existing errors are listed in `phpstan-baseline.neon`: never add new errors to it, fix them instead. When a change fixes baselined errors, regenerate it with `vendor/bin/phpstan analyse --generate-baseline --memory-limit=2G`. If Larastan reports unmatched baseline entries after a dependency update, refresh Testbench's package discovery (`vendor/bin/testbench package:discover`) and clear the result cache (`vendor/bin/phpstan clear-result-cache`) before regenerating.
- CI (GitHub Actions): `.github/workflows/tests.yml` runs the tests on PHP 8.3 and 8.4 × Laravel 11 to 13 with SQLite, and on PHP 8.4 × Laravel 11 to 13 with laravel-panneau 1.3 and with MySQL 8.4, and on PHP 8.4 × Laravel 13 with the lowest accepted Guzzle 7 and 8 releases; `.github/workflows/code-quality.yml` runs `composer validate`, Pint and Larastan.
- The commit that applied Pint to the whole codebase is listed in `.git-blame-ignore-revs`; run `git config blame.ignoreRevsFile .git-blame-ignore-revs` to skip it in `git blame`.
- Layout: `src/Folklore` (namespace `Folklore\`), `src/migrations`, `src/stubs`, `tests`.
- Active development happens on the `v1.1` branch.
