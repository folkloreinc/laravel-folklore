<?php

namespace Folklore\Tests\Feature\Panneau;

use Cviebrock\EloquentSluggable\ServiceProvider as SluggableServiceProvider;
use Folklore\Contracts\Repositories\Pages;
use Folklore\Locale\LocaleServiceProvider;
use Folklore\Mediatheque\Contracts\Mediatheque;
use Folklore\Mediatheque\Contracts\Models\Media;
use Folklore\Models\Block as BlockModel;
use Folklore\Panneau\Fields\Blocks;
use Folklore\Tests\Feature\Panneau\Fixtures\ConformanceServiceProvider;
use Folklore\Tests\Feature\Panneau\Fixtures\PagesServiceProvider;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The page fields through Panneau's API, on the pages module's repository, which stores blocks
 * in the page's data (docs/decisions/0023).
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
        $app['config']->set('filesystems.disks.media', ['driver' => 'local', 'root' => storage_path('app/media'), 'url' => 'https://media.test']);
        $app['config']->set('mediatheque.source', 'media');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateMediatheque();
        Storage::fake('media');
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
     * The blocks of a page as stored, without the cast.
     *
     * @return list<array<string, mixed>>
     */
    private function storedBlocks(string $id): array
    {
        return json_decode((string) DB::table('pages')->where('id', $id)->value('data'), true)['blocks'] ?? [];
    }

    /**
     * An image of the mediatheque, ingested on a fake disk, without its jobs.
     */
    private function image(string $name = 'Portrait'): Media
    {
        Bus::fake();
        $path = tempnam(sys_get_temp_dir(), 'image');
        // A PNG of one pixel
        file_put_contents($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        try {
            return $this->app->make(Mediatheque::class)->ingest($path, name: $name);
        } finally {
            unlink($path);
        }
    }

    /**
     * The URL of the original of a media.
     */
    private function urlOf(Media $media): string
    {
        return Storage::disk('media')->url((string) $media->original()?->path());
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
        $this->assertSame(0, BlockModel::query()->count());
        $stored = $this->storedBlocks($created->json('data.id'));
        $this->assertSame($created->json('data.blocks.0.id'), $stored[0]['id']);
        $this->assertSame($created->json('data.blocks.1.blocks.0.id'), $stored[1]['blocks'][0]['id']);
        $this->assertSame('Nested', $stored[1]['blocks'][0]['body']);
    }

    public function test_saving_blocks_again_keeps_their_ids()
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
        $this->assertCount(2, $this->storedBlocks($created->json('data.id')));
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
        $this->assertSame('Mine, edited', $this->storedBlocks($created->json('data.id'))[1]['body']);
        $this->getJson('/panneau/api/resources/pages/'.$other->json('data.id'))
            ->assertJsonPath('data.blocks.0.body', 'Other');
    }

    public function test_a_block_is_stored_in_the_page_with_its_id()
    {
        $created = $this->postJson(self::PATH, ['title' => ['fr' => 'Page'], 'blocks' => [['type' => 'text', 'body' => 'First']]])
            ->assertCreated();
        $blocks = $created->json('data.blocks');
        $blocks[0]['body'] = 'Edited';

        $this->patchJson(self::PATH.'/'.$created->json('data.id'), ['blocks' => $blocks])->assertOk();

        $this->assertSame([['id' => $blocks[0]['id'], 'type' => 'text', 'body' => 'Edited']], $this->storedBlocks($created->json('data.id')));
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

    public function test_the_images_of_blocks_are_media_references_with_their_transform()
    {
        $image = $this->image();
        $transform = ['crop' => ['x' => 0, 'y' => 0, 'width' => 1, 'height' => 1], 'rotate' => 90];

        $created = $this->postJson(self::PATH, [
            'title' => ['fr' => 'Page'],
            'blocks' => [
                ['type' => 'image', 'image' => ['id' => $image->uid, 'transform' => $transform]],
                ['type' => 'gallery', 'images' => [['id' => $image->uid]]],
            ],
        ])->assertCreated();

        foreach ([$created, $this->getJson(self::PATH.'/'.$created->json('data.id'))->assertOk()] as $response) {
            $response
                ->assertJsonPath('data.blocks.0.image.id', $image->uid)
                ->assertJsonPath('data.blocks.0.image.resource', 'images')
                ->assertJsonPath('data.blocks.0.image.label', 'Portrait')
                ->assertJsonPath('data.blocks.0.image.media.type', 'image')
                ->assertJsonPath('data.blocks.0.image.media.url', $this->urlOf($image))
                ->assertJsonPath('data.blocks.0.image.transform', $transform)
                ->assertJsonPath('data.blocks.1.images.0.id', $image->uid)
                ->assertJsonPath('data.blocks.1.images.0.media.url', $this->urlOf($image));
            $this->assertIsString($response->json('data.blocks.0.image.media.thumbnailUrl'));
            $this->assertNotSame('', $response->json('data.blocks.0.image.media.thumbnailUrl'));
            $this->assertArrayNotHasKey('transform', $response->json('data.blocks.1.images.0'));
        }
        // The block keeps what the repository received
        $this->assertSame(['id' => $image->uid, 'transform' => $transform], $this->storedBlocks($created->json('data.id'))[0]['image']);
    }

    public function test_a_media_model_in_a_block_is_a_media_reference()
    {
        $image = $this->image();
        $field = Blocks::make('blocks');

        $serialized = $field->serialize([['type' => 'image', 'image' => $image], ['type' => 'gallery', 'images' => [$image]]]);

        $this->assertIsArray($serialized);
        $reference = $serialized[0]['image'];
        $this->assertSame(['id', 'resource', 'label', 'media'], array_keys($reference));
        $this->assertSame($image->uid, $reference['id']);
        $this->assertSame('images', $reference['resource']);
        $this->assertSame($this->urlOf($image), $reference['media']['url']);
        $this->assertIsString($reference['media']['thumbnailUrl']);
        $this->assertSame($reference, $serialized[1]['images'][0]);
    }

    public function test_the_images_of_blocks_are_found_together()
    {
        $images = array_map(fn (int $index): Media => $this->image('Image '.$index), range(1, 3));
        $one = $this->postJson(self::PATH, ['title' => ['fr' => 'Une'], 'blocks' => [['type' => 'image', 'image' => ['id' => $images[0]->uid]]]])
            ->assertCreated()->json('data.id');
        $three = $this->postJson(self::PATH, ['title' => ['fr' => 'Trois'], 'blocks' => array_map(
            fn (Media $image): array => ['type' => 'image', 'image' => ['id' => $image->uid]],
            $images,
        )])->assertCreated()->json('data.id');

        $queries = function (string $id): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(self::PATH.'/'.$id)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $this->assertSame($queries($one), $queries($three));
        $this->getJson(self::PATH.'/'.$three)->assertJsonPath('data.blocks.2.image.label', 'Image 3');
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
