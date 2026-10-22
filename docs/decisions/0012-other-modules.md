# 0012: Other modules: kept, deprecated, removed

- Status: accepted
- Date: 2026-10-19

## Context

1.x ships optional features "just in case", most of them booted whether a site uses them or not, and some with heavy dependencies (`google/apiclient` for Places, `parsecsv` for Drive). Some are used by no active site.

## Decision

Each module is opt-in in `config/site.php`; nothing boots unless enabled.

**Kept:**

- Customer.io: client, mail transport, notification channel;
- Google Maps, Places and Drive. Places is rewritten on Laravel's HTTP client, which drops `google/apiclient`;
- the PubNub broadcaster;
- the view composers and `routeForReactRouter`;
- the `csv` and `pixel` response macros;
- `daemon:restart`;
- `SerializesEntityModel`, with the same payload, so queued jobs survive the upgrade;
- the `Support` concerns.

**Deprecated:** `assets:view` and `LocalMiddleware`, which serve the webpack asset manifest, until Vite helpers replace them.

**Changed:** the global override of the paginator's page resolver is removed. `OffsetPaginator` resolves its own offset.

**Removed** (no active site uses them):

- the site manager;
- organisations, with the unused `roles` table;
- PubSubHubbub;
- the `Contact`, `Person` and `Contactable` contracts, which nothing implements.

## Consequences

- `google/apiclient` and `parsecsv/php-parsecsv` leave the dependencies (`parsecsv` stays suggested for Drive's CSV export).
- `folklore:upgrade --check` reports a site that uses a removed module; it stays on 1.1 or moves the code into the application.
- The `make:*` generators stay, and are updated for the 2.0 base classes.
