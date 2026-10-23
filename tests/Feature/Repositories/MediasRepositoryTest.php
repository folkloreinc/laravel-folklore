<?php

namespace Folklore\Tests\Feature\Repositories;

use Folklore\Contracts\Entities\Image;
use Folklore\Contracts\Repositories\Medias;
use Folklore\Mediatheque\Jobs\ProbeMedia;
use Folklore\Models\Media;
use Folklore\Tests\TestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;

/**
 * The medias repository creates media through laravel-mediatheque 2's ingest.
 */
class MediasRepositoryTest extends TestCase
{
    private Medias $medias;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateMediatheque();
        Storage::fake('public');
        Bus::fake([ProbeMedia::class]);
        $this->medias = $this->app->make(Medias::class);
    }

    public function test_it_creates_a_media_from_a_file()
    {
        $media = $this->medias->createFromFile(new File(public_path('folklore.png')), ['name' => 'Logo']);

        $this->assertInstanceOf(Image::class, $media);
        $this->assertSame('Logo', $media->name());
        $this->assertSame('image', $media->type());
        $this->assertSame('original', $media->files()->first()?->handle());
        $this->assertStringStartsWith('med_', $media->getModel()->uid);
        $this->assertInstanceOf(Media::class, $media->getModel());
        Storage::disk('public')->assertExists((string) $media->getModel()->original()?->path());
        Bus::assertDispatched(ProbeMedia::class);
    }

    public function test_it_finds_a_media_by_its_id_and_by_its_name()
    {
        $media = $this->medias->createFromFile(new File(public_path('folklore.png')), ['name' => 'Logo']);

        $this->assertSame($media->id(), $this->medias->findById($media->id())?->id());
        $this->assertSame($media->id(), $this->medias->findByName('Logo')?->id());
        $this->assertNull($this->medias->findByName('Missing'));
    }

    public function test_it_replaces_the_original_of_a_media()
    {
        $media = $this->medias->createFromFile(new File(public_path('folklore.png')), ['name' => 'Logo']);
        $original = $media->getModel()->original()?->path();

        $replaced = $this->medias->updateFromFile($media->id(), new File(__DIR__.'/../../fixture/folklore.png'), ['name' => 'New logo']);

        $this->assertSame($media->id(), $replaced->id());
        $this->assertSame('New logo', $replaced->name());
        $this->assertNotNull($replaced->getModel()->original()?->path());
        $this->assertIsString($original);
    }

    public function test_it_lists_media_by_type_with_the_newest_first()
    {
        $first = $this->medias->createFromFile(new File(public_path('folklore.png')), ['name' => 'First']);
        $this->travel(1)->minutes();
        $second = $this->medias->createFromFile(new File(public_path('folklore.png')), ['name' => 'Second']);

        $this->assertSame([$second->id(), $first->id()], $this->medias->get(['type' => 'image'])->map->id()->all());
        $this->assertSame([], $this->medias->get(['type' => 'video'])->all());
        $this->assertSame([$first->id()], $this->medias->get(['search' => 'first'])->map->id()->all());
    }
}
