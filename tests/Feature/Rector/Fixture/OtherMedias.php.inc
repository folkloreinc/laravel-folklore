<?php

namespace App\Http\Controllers;

use App\Repositories\Medias;

class OtherMediasController
{
    public function index($typeFactory)
    {
        return new Medias($typeFactory);
    }
}
