<?php

namespace Folklore\Tests\Feature;

use Folklore\Tests\TestCase;

class PagesTest extends TestCase
{
    public function testCreatePage()
    {
        $repository = $this->app->make(\Folklore\Contracts\Repositories\Pages::class);
        $page = $repository->create([
            'title' => [
                'fr' => 'Une page',
                'fr' => 'A page',
            ],
        ]);
        dd($page);
    }
}
