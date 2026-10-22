# 0010: Users and auth are an explicit opt-in module

- Status: accepted
- Date: 2026-10-19

## Context

1.x always registers the `repository` auth provider and two global Fortify callbacks (`authenticateUsing`, `confirmPasswordsUsing`), and requires Fortify. The `User` entity implements two-factor authentication over the model. Sites on 1.1 rely on them.

A project can require Fortify and handle authentication itself: the pilot does.

## Decision

- The `repository` auth driver, the `User` entity with two-factor authentication, and the Fortify callbacks boot only when the module is **enabled in `config/site.php`**.
- Enabling them whenever Fortify is installed is not an option.
- `laravel/fortify` moves to `suggest`.
- `folklore:upgrade` enables the module for upgraded sites.

## Consequences

- A project that doesn't enable the module gets no auth provider and no Fortify callback from the package.
- The module's tests run with Fortify installed; the core's tests run without it.
