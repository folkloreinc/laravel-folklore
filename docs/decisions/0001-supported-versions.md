# 0001: PHP 8.3+ and Laravel 12+

- Status: accepted
- Date: 2026-10-22

## Context

1.1 supports PHP 8.3+ and Laravel 11 to 13. A new major line can require more when the projects that will use it are all more recent.

- The packages 2.0 builds on require PHP 8.3+ and Laravel 12+: `laravel-mediatheque` 2, `laravel-image` 2 and `laravel-panneau` 5.
- The two pilots run Laravel 13: Niche on PHP 8.5, the contests platform on PHP 8.4 and 8.5.
- Some active sites on 1.1 run Laravel 11 and PHP 8.3.
- Laravel 12 gets security fixes until February 2027, PHP 8.3 until the end of 2027.

The plan in [#56](https://github.com/folkloreinc/laravel-folklore/issues/56#issuecomment-6271513875) chose PHP 8.3+ and Laravel 12+. Requiring PHP 8.4 or Laravel 13 would fit both pilots, but not the packages 2.0 builds on, nor the 1.1 sites on Laravel 12. The choice was confirmed in phase 0, before the tooling.

## Decision

- 2.x requires **PHP 8.3+** and **Laravel 12+**, like the packages it builds on.
- CI tests PHP 8.3 to 8.5 against Laravel 12 and 13.
- Dropping Laravel 12 or PHP 8.3 happens in a minor release, once no active 2.x project uses them.

## Consequences

- The code uses PHP 8.3 features (enums, readonly properties, typed class constants), and nothing newer.
- A site on Laravel 11 upgrades Laravel first, then `laravel-folklore`. It stays on 1.1 until then.
- 1.1 keeps PHP 8.3+ and Laravel 11+, for fixes and security.
