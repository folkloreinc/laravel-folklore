<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Tests\Feature\Panneau\Fixtures\ConformanceServiceProvider;
use Folklore\Tests\Feature\Repositories\Fixtures\Article;
use Folklore\Tests\Feature\Repositories\Fixtures\ArticleEntity;
use Folklore\Tests\TestCase;
use Panneau\ServiceProvider as PanneauServiceProvider;

/**
 * Panneau with the articles resource, on the articles repository (ConformanceServiceProvider).
 */
abstract class PanneauTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--path' => __DIR__.'/../Repositories/Fixtures/migrations',
            '--realpath' => true,
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            PanneauServiceProvider::class,
            ConformanceServiceProvider::class,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function article(array $attributes = []): ArticleEntity
    {
        return Article::query()->create(array_merge([
            'workspace' => 'folklore',
            'title' => 'An article',
            'status' => 'published',
        ], $attributes))->toEntity();
    }
}
