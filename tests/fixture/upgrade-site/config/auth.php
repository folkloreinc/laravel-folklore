<?php

use App\Contracts\Repositories\Users;

return [
    'providers' => [
        'users' => [
            'driver' => 'repository',
            'repository' => Users::class,
        ],
    ],
];
