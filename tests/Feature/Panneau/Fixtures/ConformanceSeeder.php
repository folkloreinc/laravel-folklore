<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures;

use Folklore\Tests\Feature\Repositories\Fixtures\Article;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Three articles of the resource's workspace, and one of another workspace, which the
 * resource never shows.
 */
class ConformanceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['First article', 'Second article', 'Third article'] as $index => $title) {
            Article::query()->create([
                'workspace' => 'folklore',
                'title' => $title,
                'slug_fr' => 'article-'.($index + 1),
                'slug_en' => Str::slug($title),
                'status' => 'published',
                'published_at' => now()->subDays($index),
            ]);
        }
        Article::query()->create([
            'workspace' => 'urbania',
            'title' => 'Another workspace',
            'slug_fr' => 'autre',
            'slug_en' => 'other',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
