<?php

namespace App\Repositories;

use Folklore\Repositories\Entities;

final class FinalArticles extends Entities
{
    protected $jsonAttributeName = 'content';
}
-----
<?php

namespace App\Repositories;

use Folklore\Repositories\Entities;

final class FinalArticles extends Entities
{
    protected $jsonAttributes = ['content'];
}
