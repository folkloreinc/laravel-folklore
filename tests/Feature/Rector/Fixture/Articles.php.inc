<?php

namespace App\Repositories;

use App\Models\Article;
use Folklore\Repositories\Entities;

class Articles extends Entities
{
    protected $jsonAttributeName = 'content';

    protected function newModel()
    {
        return new Article();
    }
}
-----
<?php

namespace App\Repositories;

use App\Models\Article;
use Folklore\Repositories\Entities;

class Articles extends Entities
{
    protected $jsonAttributeName = 'content';
    protected $jsonAttributes = ['content'];

    protected function newModel()
    {
        return new Article();
    }
}
