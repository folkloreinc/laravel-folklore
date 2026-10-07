<?php

namespace Folklore\Tests\Feature\Repositories;

use Cviebrock\EloquentSluggable\ServiceProvider as SluggableServiceProvider;
use Folklore\Contracts\Repositories\Blocks;
use Folklore\Contracts\Repositories\Pages;
use Folklore\Models\Block as BlockModel;
use Folklore\Models\Page as PageModel;
use Folklore\Tests\TestCase;
use RuntimeException;

class PagesRepositoryTest extends TestCase
{
    protected Pages $pages;

    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [SluggableServiceProvider::class]);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('locale.locales', ['fr', 'en']);
        $app['config']->set('app.fallback_locale', 'fr');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'testbench']);
        foreach (['000000_create_pages_table', '000001_create_blocks_table', '000002_create_blocks_pivot'] as $migration) {
            $this->artisan('migrate', [
                '--database' => 'testbench',
                '--path' => __DIR__.'/../../../src/migrations/2021_01_01_'.$migration.'.php',
                '--realpath' => true,
            ]);
        }

        $this->pages = $this->app->make(Pages::class);
    }

    public function test_it_saves_a_page_with_its_blocks()
    {
        $page = $this->pages->create([
            'handle' => 'about',
            'title' => ['fr' => 'À propos', 'en' => 'About'],
            'blocks' => [
                ['type' => 'text', 'body' => 'First'],
                ['type' => 'text', 'body' => 'Second'],
            ],
        ]);

        $this->assertSame('À propos', $page->title('fr'));
        $this->assertSame('a-propos', $page->slug('fr'));
        $this->assertSame(
            ['First', 'Second'],
            $page->blocks()->map(fn ($block) => $block->data()['body'])->all(),
        );
        $this->assertSame(1, PageModel::count());
        $this->assertSame(2, BlockModel::count());
    }

    public function test_a_failure_while_saving_a_page_leaves_no_block_behind()
    {
        PageModel::saving(function () {
            throw new RuntimeException('Saving the page failed.');
        });

        try {
            $this->pages->create([
                'handle' => 'about',
                'title' => ['fr' => 'À propos'],
                'blocks' => [['type' => 'text', 'body' => 'First']],
            ]);
            $this->fail('Saving the page should have failed.');
        } catch (RuntimeException $e) {
            $this->assertSame('Saving the page failed.', $e->getMessage());
        }

        $this->assertSame(0, PageModel::count());
        $this->assertSame(0, BlockModel::count());
    }

    public function test_a_failure_while_saving_nested_blocks_rolls_back_the_parent_block()
    {
        $blocks = $this->app->make(Blocks::class);
        BlockModel::saving(function (BlockModel $block) {
            if (data_get($block->getAttributes(), 'type') === 'broken') {
                throw new RuntimeException('Saving the block failed.');
            }
        });

        try {
            $blocks->create([
                'type' => 'group',
                'blocks' => [
                    ['type' => 'text', 'body' => 'First'],
                    ['type' => 'broken'],
                ],
            ]);
            $this->fail('Saving the block should have failed.');
        } catch (RuntimeException $e) {
            $this->assertSame('Saving the block failed.', $e->getMessage());
        }

        $this->assertSame(0, BlockModel::count());
    }

    public function test_destroying_a_page_soft_deletes_it()
    {
        $page = $this->pages->create([
            'handle' => 'about',
            'title' => ['fr' => 'À propos'],
            'blocks' => [['type' => 'text', 'body' => 'First']],
        ]);

        $this->assertTrue($this->pages->destroy($page->id()));

        $this->assertNull($this->pages->findById($page->id()));
        $this->assertNull($this->pages->findByHandle('about'));
        $this->assertNull($this->pages->findBySlug('a-propos', 'fr'));
        $this->assertSame(0, $this->pages->count());

        $model = PageModel::withTrashed()->with('blocks')->findOrFail($page->id());
        $this->assertTrue($model->trashed());
        $this->assertCount(1, $model->blocks);
    }
}
