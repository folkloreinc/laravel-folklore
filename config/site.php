<?php

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

    'modules' => [
        // Medias repository and entities, on laravel-mediatheque and laravel-image
        'medias' => false,

        // Pages and blocks repositories, on eloquent-sluggable and laravel-locale
        'pages' => false,

        // The `repository` auth provider, the users repository, the Fortify
        // callbacks and `users:create`, on laravel/fortify
        'auth' => false,

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
