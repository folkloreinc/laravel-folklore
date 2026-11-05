<?php

namespace Folklore\Tests\Feature\Panneau;

use Cviebrock\EloquentSluggable\ServiceProvider as SluggableServiceProvider;
use Folklore\Entities\Blocks\ButtonBlock;
use Folklore\Entities\Blocks\EmbedBlock;
use Folklore\Entities\Blocks\GalleryBlock;
use Folklore\Entities\Blocks\HeadingBlock;
use Folklore\Entities\Blocks\ImageBlock;
use Folklore\Entities\Blocks\QuoteBlock;
use Folklore\Entities\Blocks\TextBlock;
use Folklore\Locale\LocaleServiceProvider;
use Folklore\Mediatheque\Contracts\Mediatheque;
use Folklore\Mediatheque\Contracts\Models\Media;
use Folklore\Tests\Feature\Panneau\Fixtures\ConformanceServiceProvider;
use Folklore\Tests\Feature\Panneau\Fixtures\StandardResourcesServiceProvider;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

/**
 * Panneau with a site's standard resources (StandardResourcesServiceProvider): pages with the
 * standard block types, users and the media library.
 */
abstract class StandardResourcesTestCase extends PanneauTestCase
{
    /**
     * The standard block types, as a site declares them.
     */
    public const BLOCK_TYPES = [
        'text' => TextBlock::class,
        'heading' => HeadingBlock::class,
        'image' => ImageBlock::class,
        'gallery' => GalleryBlock::class,
        'quote' => QuoteBlock::class,
        'button' => ButtonBlock::class,
        'embed' => EmbedBlock::class,
    ];

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            SluggableServiceProvider::class,
            LocaleServiceProvider::class,
            StandardResourcesServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('locale.locales', ['fr', 'en']);
        $app['config']->set('app.locale', 'fr');
        $app['config']->set('app.fallback_locale', 'fr');
        $app['config']->set('site.pages.blocks.types', self::BLOCK_TYPES);
        $app['config']->set('filesystems.disks.media', ['driver' => 'local', 'root' => storage_path('app/media'), 'url' => 'https://media.test']);
        $app['config']->set('mediatheque.source', 'media');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testbench']);
        $this->migrateMediatheque();
        Storage::fake('media');
        foreach (['2020_01_01_000000_add_role_to_users', '2021_01_01_000000_create_pages_table'] as $migration) {
            $this->artisan('migrate', [
                '--database' => 'testbench',
                '--path' => __DIR__.'/../../../src/migrations/'.$migration.'.php',
                '--realpath' => true,
            ]);
        }
        $this->withToken(ConformanceServiceProvider::TOKEN);
    }

    /**
     * The definition of a resource.
     *
     * @return array<string, mixed>
     */
    protected function resourceDefinition(string $id): array
    {
        $resources = $this->getJson('/panneau/api/definition')->assertOk()->json('resources');
        foreach ($resources as $resource) {
            if ($resource['id'] === $id) {
                return $resource;
            }
        }
        $this->fail('No resource '.$id);
    }

    /**
     * An image of the mediatheque, ingested on a fake disk, without its jobs.
     */
    protected function image(string $name = 'Portrait'): Media
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
}
