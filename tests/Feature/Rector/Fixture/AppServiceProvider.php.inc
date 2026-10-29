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

    protected function repository(?Repository $repository = null): Repository|Posts|null
    {
        return $repository ?? $this->app->make(Repository::class);
    }
}
-----
<?php

namespace App\Providers;

use Folklore\JsonData\JsonData;
use Folklore\Contracts\Repositories\Entities;
use App\Models\Post;
use App\Repositories\Posts;
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

    protected function repository(?Entities $repository = null): Entities|Posts|null
    {
        return $repository ?? $this->app->make(Repository::class);
    }
}
