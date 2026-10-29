<?php

namespace App\Http\Controllers;

use Folklore\Repositories\Medias;

class MediasController
{
    public function index($typeFactory)
    {
        return (new Medias($typeFactory))->get();
    }
}
-----
<?php

namespace App\Http\Controllers;

use Folklore\Repositories\Medias;

class MediasController
{
    public function index($typeFactory)
    {
        return (new Medias())->get();
    }
}
