<?php

namespace App\Models;

use Folklore\Mediatheque\Support\Traits\HasMedias;
use Folklore\Models\Page as BasePage;

class Page extends BasePage
{
    use HasMedias;
}
-----
<?php

namespace App\Models;

use Folklore\Mediatheque\Concerns\HasMedias;
use Folklore\Models\Page as BasePage;

class Page extends BasePage
{
    use HasMedias;
}
