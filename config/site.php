<?php

use Folklore\Models\Page;

return [

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | The modules of laravel-folklore that this site uses. A module that is off
    | registers and boots nothing: no binding, macro, middleware, auth provider
    | or command. A module that is on checks that the packages it needs are
    | installed. See docs/architecture.md, "Configuration and modules".
    |
    */

    // The locales of translatable values in JSON data schemas
    // (`Json::string()->translatable()`). Null: laravel-locale's `locale.locales` when it
    // is set, else laravel-json-data's `json-data.locales`.
    'locales' => null,

    // The site's repositories, by contract: each contract, the package's ones included, is
    // bound to its class (`folklore:install` writes those of the standard resources).
    'repositories' => [],

    'medias' => [
        // The key written for media references in JSON data: `media` (`media://med_…`,
        // by uid) for new projects, `medias` (`medias://12`, by id) for sites upgraded
        // from 1.x, whose front ends read it. Both are read.
        'reference_key' => 'media',

        // The JSON shape of `Http\Resources\MediaResource`: `legacy`, the shape of 1.1, which
        // front ends written for 1.x read, until 3.0; `canonical`, laravel-mediatheque's, for
        // new projects and front ends that moved to it.
        'resource' => 'legacy',

        // The URLs and dimensions of image sizes (docs/decisions/0024): `presets`, the URL of
        // each laravel-image 2 preset, which `Route::image()` serves, for new projects;
        // `legacy`, the 1.x URL of each size of `image.sizes` (`-filters(large)`), which
        // `Route::imageLegacy()` serves, with 1.1's dimensions, for sites upgraded from 1.x
        // until they move to presets and `Route::image()`.
        'image_urls' => 'presets',
    ],

    'pages' => [
        // The names of the routes of pages, without their locale: the home page, a page
        // (`{page}`, its slug) and a page with a parent (`{parent}/{page}`). A name is
        // looked up as laravel-locale's localized route (`{locale}.{name}`) first, then
        // as a route without a locale. Without a `page_with_parent` route, pages with a
        // parent are at `page`. Page entities and the `PageSlug` field of the Panneau
        // plugin give URLs from them.
        'routes' => [
            'home' => 'home',
            'page' => 'page',
            'page_with_parent' => 'page_with_parent',
        ],

        // The model of the pages, which the `page` key of JSON references is mapped to
        // (`page://12`): the site's own when it extends Folklore\Models\Page.
        'model' => Page::class,

        // The blocks of pages, stored in their JSON (docs/decisions/0023): the entity
        // class of each type, which extends Folklore\Entities\Block and declares the
        // schema of its data (`blockSchema()`), and the deepest level of blocks.
        'blocks' => [
            'types' => [],
            'depth' => 3,
        ],
    ],

    'modules' => [
        // Medias repository and entities, on laravel-mediatheque and laravel-image
        'medias' => false,

        // Pages and blocks repositories, on eloquent-sluggable and laravel-locale
        'pages' => false,

        // The `repository` auth provider, the users repository, the Fortify
        // callbacks and `users:create`, on laravel/fortify
        'auth' => false,

        // The Panneau 5 plugin: the adapter of the repositories for Panneau's
        // resources, on folklore/laravel-panneau
        'panneau' => false,

        // Organisations repository (removed in 2.0)
        'organisations' => false,

        // Site manager (removed in 2.0)
        'sites' => false,

        // `routeForReactRouter`, the `pixel` and `csv` responses, and the offset
        // page resolver of OffsetPaginator
        'http' => false,

        // `assets:view`, and LocalMiddleware in the local environment (deprecated)
        'assets' => false,

        // Customer.io client, mail transport and notification channel, when
        // `services.customerio` is set
        'customerio' => false,

        // Google Maps, Places and Drive
        'google' => false,

        // The `pubnub` broadcast driver, on pubnub/pubnub
        'pubnub' => false,

        // PubSubHubbub client and commands, when `pubsubhubbub` is set (removed in 2.0)
        'pubsubhubbub' => false,
    ],

];
