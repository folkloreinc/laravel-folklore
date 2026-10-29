<?php

namespace Folklore\Tests\Feature\Panneau;

use Cviebrock\EloquentSluggable\ServiceProvider as SluggableServiceProvider;
use Folklore\Contracts\Repositories\Pages;
use Folklore\Locale\LocaleServiceProvider;
use Folklore\Models\Block as BlockModel;
use Folklore\Tests\Feature\Panneau\Fixtures\ConformanceServiceProvider;
use Folklore\Tests\Feature\Panneau\Fixtures\PagesServiceProvider;
use Illuminate\Support\Facades\DB;

/**
 * The page fields through Panneau's API, on the pages module's repository, which stores blocks
 * as rows of `blocks` linked through `blocks_pivot`.
 */
class PageFieldsApiTest extends PanneauTestCase
{
    private const PATH = '/panneau/api/resources/pages';

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            SluggableServiceProvider::class,
            LocaleServiceProvider::class,
            PagesServiceProvider::class,
        ];
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

        $this->migrateMediatheque();
        foreach (['000000_create_pages_table', '000001_create_blocks_table', '000002_create_blocks_pivot'] as $migration) {
            $this->artisan('migrate', [
                '--database' => 'testbench',
                '--path' => __DIR__.'/../../../src/migrations/2021_01_01_'.$migration.'.php',
                '--realpath' => true,
            ]);
        }
        $this->withToken(ConformanceServiceProvider::TOKEN);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(array $data): string
    {
        return $this->app->make(Pages::class)->create($data)->id();
    }

    public function test_a_page_is_saved_with_its_blocks_and_nested_blocks()
    {
        $created = $this->postJson(self::PATH, [
            'title' => ['fr' => 'À propos', 'en' => 'About'],
            'type' => 'page',
            'blocks' => [
                ['type' => 'text', 'body' => 'First'],
                ['type' => 'stack', 'title' => 'Stack', 'blocks' => [['type' => 'text', 'body' => 'Nested']]],
            ],
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.slug.fr', 'a-propos')
            ->assertJsonPath('data.blocks.0.type', 'text')
            ->assertJsonPath('data.blocks.0.body', 'First')
            ->assertJsonPath('data.blocks.1.type', 'stack')
            ->assertJsonPath('data.blocks.1.title', 'Stack')
            ->assertJsonPath('data.blocks.1.blocks.0.type', 'text')
            ->assertJsonPath('data.blocks.1.blocks.0.body', 'Nested');
        $this->assertSame(3, BlockModel::query()->count());
        $this->assertSame(3, DB::table('blocks_pivot')->count());
        $this->assertIsString($created->json('data.blocks.0.id'));
        $this->assertIsString($created->json('data.blocks.1.blocks.0.id'));
    }

    public function test_saving_blocks_again_updates_their_rows()
    {
        $created = $this->postJson(self::PATH, [
            'title' => ['fr' => 'À propos'],
            'blocks' => [
                ['type' => 'text', 'body' => 'First'],
                ['type' => 'stack', 'title' => 'Stack', 'blocks' => [['type' => 'text', 'body' => 'Nested']]],
            ],
        ])->assertCreated();
        $blocks = $created->json('data.blocks');
        $blocks[0]['body'] = 'First, edited';
        $blocks[1]['blocks'][0]['body'] = 'Nested, edited';

        $updated = $this->patchJson(self::PATH.'/'.$created->json('data.id'), ['blocks' => array_reverse($blocks)]);

        $updated->assertOk()
            ->assertJsonPath('data.blocks.0.id', $blocks[1]['id'])
            ->assertJsonPath('data.blocks.0.blocks.0.id', $blocks[1]['blocks'][0]['id'])
            ->assertJsonPath('data.blocks.0.blocks.0.body', 'Nested, edited')
            ->assertJsonPath('data.blocks.1.id', $blocks[0]['id'])
            ->assertJsonPath('data.blocks.1.body', 'First, edited');
        $this->assertSame(3, BlockModel::query()->count());
    }

    public function test_a_block_keeps_an_id_only_if_it_is_one_of_the_pages_blocks_once()
    {
        $other = $this->postJson(self::PATH, ['title' => ['fr' => 'Autre'], 'blocks' => [['type' => 'text', 'body' => 'Other']]])
            ->assertCreated();
        $otherBlock = $other->json('data.blocks.0.id');
        $created = $this->postJson(self::PATH, ['title' => ['fr' => 'Page'], 'blocks' => [['type' => 'text', 'body' => 'Mine']]])
            ->assertCreated();
        $mine = $created->json('data.blocks.0.id');

        $updated = $this->patchJson(self::PATH.'/'.$created->json('data.id'), ['blocks' => [
            ['id' => $otherBlock, 'type' => 'text', 'body' => 'Taken'],
            ['id' => $mine, 'type' => 'text', 'body' => 'Mine, edited'],
            ['id' => $mine, 'type' => 'text', 'body' => 'A copy'],
        ]]);

        $updated->assertOk()->assertJsonPath('data.blocks.1.id', $mine);
        $this->assertNotSame($otherBlock, $updated->json('data.blocks.0.id'));
        $this->assertNotSame($mine, $updated->json('data.blocks.2.id'));
        $this->assertSame('Other', BlockModel::query()->findOrFail($otherBlock)->data['body']);
        $this->assertSame('Mine, edited', BlockModel::query()->findOrFail($mine)->data['body']);
        $this->getJson('/panneau/api/resources/pages/'.$other->json('data.id'))
            ->assertJsonPath('data.blocks.0.body', 'Other');
    }

    public function test_the_id_of_a_block_is_not_saved_in_its_data()
    {
        $created = $this->postJson(self::PATH, ['title' => ['fr' => 'Page'], 'blocks' => [['type' => 'text', 'body' => 'First']]])
            ->assertCreated();
        $blocks = $created->json('data.blocks');
        $blocks[0]['body'] = 'Edited';

        $this->patchJson(self::PATH.'/'.$created->json('data.id'), ['blocks' => $blocks])->assertOk();

        $this->assertSame(['body' => 'Edited'], BlockModel::query()->findOrFail($blocks[0]['id'])->data);
    }

    public function test_the_version_of_a_page_changes_with_its_blocks()
    {
        $created = $this->postJson(self::PATH, [
            'title' => ['fr' => 'Page'],
            'blocks' => [['type' => 'stack', 'title' => 'Stack', 'blocks' => [['type' => 'text', 'body' => 'Nested']]]],
        ])->assertCreated();
        $version = $created->json('data.version');
        $blocks = $created->json('data.blocks');
        $blocks[0]['blocks'][0]['body'] = 'Nested, edited';

        $updated = $this->patchJson(self::PATH.'/'.$created->json('data.id'), ['blocks' => $blocks], ['If-Match' => '"'.$version.'"']);

        $updated->assertOk();
        $this->assertNotSame($version, $updated->json('data.version'));
        // A form opened before the edit can't overwrite it
        $blocks[0]['blocks'][0]['body'] = 'Stale';
        $this->patchJson(self::PATH.'/'.$created->json('data.id'), ['blocks' => $blocks], ['If-Match' => '"'.$version.'"'])
            ->assertStatus(412);
        $this->getJson(self::PATH.'/'.$created->json('data.id'))
            ->assertJsonPath('data.version', $updated->json('data.version'));
    }

    public function test_the_slug_of_a_page_is_unique_across_the_types_of_pages()
    {
        $this->page(['title' => ['fr' => 'Été'], 'type' => 'page']);

        $this->postJson(self::PATH, ['title' => ['fr' => 'Autre'], 'slug' => ['fr' => 'ete']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug.fr']);
        // A resource of sections sees the slugs of the other types of pages too
        $this->postJson('/panneau/api/resources/sections', ['title' => ['fr' => 'Rubrique'], 'slug' => ['fr' => 'ete']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug.fr']);
        $this->postJson('/panneau/api/resources/sections/fields/slug/check', ['value' => 'ete', 'locale' => 'fr', 'id' => null, 'values' => []])
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('suggestion', 'ete-2');
    }

    public function test_the_related_pages_of_blocks_are_found_together()
    {
        $sections = array_map(fn (int $index): string => $this->page(['title' => ['fr' => 'Rubrique '.$index], 'type' => 'section']), range(1, 3));
        $one = $this->postJson(self::PATH, ['title' => ['fr' => 'Une'], 'blocks' => [['type' => 'section', 'section' => $sections[0]]]])
            ->assertCreated()->json('data.id');
        $three = $this->postJson(self::PATH, ['title' => ['fr' => 'Trois'], 'blocks' => array_map(
            fn (string $section): array => ['type' => 'section', 'section' => $section],
            $sections,
        )])->assertCreated()->json('data.id');

        $queries = function (string $id): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(self::PATH.'/'.$id)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $this->assertSame($queries($one), $queries($three));
        $this->getJson(self::PATH.'/'.$three)->assertJsonPath('data.blocks.2.section.label.fr', 'Rubrique 3');
    }

    public function test_a_block_type_is_offered_only_if_the_field_allows_it()
    {
        $this->postJson(self::PATH, ['title' => ['fr' => 'Page'], 'blocks' => [['type' => 'unknown']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['blocks.0.type']);
    }

    public function test_a_page_relation_offers_the_pages_of_its_resource_only()
    {
        $section = $this->page(['title' => ['fr' => 'Rubrique'], 'type' => 'section']);
        $page = $this->page(['title' => ['fr' => 'Page'], 'type' => 'page']);

        $this->postJson(self::PATH, ['title' => ['fr' => 'Avec une page'], 'blocks' => [['type' => 'section', 'section' => $page]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['blocks.0.section']);

        $created = $this->postJson(self::PATH, [
            'title' => ['fr' => 'Avec une rubrique'],
            'parent' => $page,
            'blocks' => [['type' => 'section', 'section' => $section]],
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.parent.id', $page)
            ->assertJsonPath('data.parent.label.fr', 'Page')
            ->assertJsonPath('data.blocks.0.section.id', $section)
            ->assertJsonPath('data.blocks.0.section.label.fr', 'Rubrique');
        $this->getJson('/panneau/api/resources/sections')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $section);
    }

    public function test_the_slug_check_gives_the_url_of_the_page_under_its_parent()
    {
        $parent = $this->page(['title' => ['fr' => 'Vacances', 'en' => 'Holidays']]);

        $this->postJson(self::PATH.'/fields/slug/check', ['value' => 'ete', 'locale' => 'fr', 'id' => null, 'values' => []])
            ->assertOk()
            ->assertJsonPath('url', url('fr/ete'))
            ->assertJsonPath('prefix', url('fr').'/')
            ->assertJsonPath('disabled', false);
        $this->postJson(self::PATH.'/fields/slug/check', ['value' => 'summer', 'locale' => 'en', 'id' => null, 'values' => ['parent' => $parent]])
            ->assertOk()
            ->assertJsonPath('url', url('en/holidays/summer'))
            ->assertJsonPath('prefix', url('en/holidays').'/');
        // A parent that isn't a page: no URL
        $this->postJson(self::PATH.'/fields/slug/check', ['value' => 'ete', 'locale' => 'fr', 'id' => null, 'values' => ['parent' => '999']])
            ->assertOk()
            ->assertJsonPath('url', null);
    }

    public function test_a_page_has_the_url_of_the_routes_of_the_config()
    {
        $parent = $this->page(['title' => ['fr' => 'Vacances']]);
        $page = $this->app->make(Pages::class)->create(['title' => ['fr' => 'Été'], 'parent' => $parent]);

        $this->assertSame(url('fr/vacances/ete'), $page->url('fr', true));
        $this->app['config']->set('site.pages.routes.page_with_parent', 'section_page');
        $this->assertSame('/fr/rubriques/vacances/ete', $page->url('fr'));
    }

    public function test_the_slug_of_the_home_page_is_disabled()
    {
        $home = $this->page(['handle' => 'home', 'title' => ['fr' => 'Accueil']]);

        $this->postJson(self::PATH.'/fields/slug/check', ['value' => '', 'locale' => 'fr', 'id' => $home, 'values' => []])
            ->assertOk()
            ->assertJsonPath('disabled', true);
    }

    public function test_the_slug_is_saved_per_locale()
    {
        $created = $this->postJson(self::PATH, ['title' => ['fr' => 'Été', 'en' => 'Summer'], 'slug' => ['fr' => 'ete-2026', 'en' => 'summer-2026']]);

        $created->assertCreated()
            ->assertJsonPath('data.slug.fr', 'ete-2026')
            ->assertJsonPath('data.slug.en', 'summer-2026');
    }
}
