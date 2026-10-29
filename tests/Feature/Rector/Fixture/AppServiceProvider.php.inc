<?php

namespace App\Providers;

use App\Models\Post;
use App\Repositories\Posts;
use Folklore\Eloquent\JsonData;
use Illuminate\Support\ServiceProvider;
use Panneau\Contracts\Repository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Repository::class, Posts::class);
    }

    public function boot(): void
    {
        JsonData::map(['post' => Post::class]);
    }
}
-----
<?php

namespace App\Providers;

use Folklore\Contracts\Repositories\Entities;
use Folklore\JsonData\JsonData;
use App\Models\Post;
use App\Repositories\Posts;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Entities::class, Posts::class);
    }

    public function boot(): void
    {
        JsonData::map(['post' => Post::class]);
    }
}
