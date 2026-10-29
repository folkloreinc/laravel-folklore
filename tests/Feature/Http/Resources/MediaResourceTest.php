<?php

namespace Folklore\Tests\Feature\Http\Resources;

use Folklore\Contracts\Entities\Media as MediaEntity;
use Folklore\Contracts\Entities\MediaMetadata;
use Folklore\Entities\Audio;
use Folklore\Entities\Document;
use Folklore\Entities\Image;
use Folklore\Entities\Video;
use Folklore\Http\Resources\MediaResource;
use Folklore\Http\Resources\MediasCollection;
use Folklore\Image\Facades\Image as ImageFacade;
use Folklore\Mediatheque\Http\Resources\MediaResource as MediathequeMediaResource;
use Folklore\Mediatheque\Models\File;
use Folklore\Models\Media;
use Folklore\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * `MediaResource` gives the shape of 1.1 by default (`legacy`), or the mediatheque's
 * canonical shape: the expected arrays below are what 1.1's resource gave for each type
 * of media, key for key and in the same order, files included.
 */
class MediaResourceTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('filesystems.disks.public.url', 'https://cdn.test/storage');
        $app['config']->set('image.strict', false);
        $app['config']->set('image.presets', [
            'card' => ['width' => 800, 'format' => 'jpeg'],
            'small' => ['width' => 400, 'format' => 'webp'],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateMediatheque();
    }

    public function test_the_legacy_shape_of_an_image_is_the_one_of_1_1()
    {
        $media = $this->makeMedia('image', 'Photo', [
            ['original', 'original', null, 'photo.jpg', 'image/jpeg', 1024, ['width' => 2000, 'height' => 1000, 'metadata' => ['filename' => 'My photo.jpg']]],
        ]);
        $image = $this->entity($media, Image::class);
        [$original] = $this->files($media);

        $this->assertSame([
            'id' => (string) $media->getKey(),
            'type' => 'image',
            'name' => 'Photo',
            'url' => 'https://cdn.test/storage/medias/photo.jpg',
            'thumbnail_url' => 'https://cdn.test/storage/medias/photo.jpg',
            'metadata' => [
                'filename' => 'My photo.jpg',
                'size' => 1024,
                'mime' => 'image/jpeg',
                'width' => 2000,
                'height' => 1000,
            ],
            'sizes' => [
                [
                    'id' => 'card',
                    'url' => ImageFacade::for($media)->preset('card')->url(),
                    'width' => 800,
                    'height' => 400,
                    'mime' => 'image/jpeg',
                ],
                [
                    'id' => 'small',
                    'url' => ImageFacade::for($media)->preset('small')->url(),
                    'width' => 400,
                    'height' => 200,
                    'mime' => 'image/webp',
                ],
            ],
            'files' => [
                [
                    'id' => (string) $original->getKey(),
                    'handle' => 'original',
                    'name' => 'My photo.jpg',
                    'url' => 'https://cdn.test/storage/medias/photo.jpg',
                    'mime' => 'image/jpeg',
                    'size' => 1024,
                ],
            ],
        ], $this->render((new MediaResource($image))->legacy()));
    }

    public function test_the_legacy_shape_of_a_video_is_the_one_of_1_1()
    {
        $media = $this->makeMedia('video', 'Clip', [
            ['original', 'original', null, 'clip.mov', 'video/quicktime', 4096, ['width' => 1920, 'height' => 1080, 'duration' => 12.5]],
            ['h264', 'main', null, 'h264.mp4', 'video/mp4', 2048, ['width' => 1920, 'height' => 1080, 'duration' => 12.5]],
            ['thumbnails', 'thumbnail', 0, 'thumbnail-0.jpg', 'image/jpeg', 64, ['width' => 640, 'height' => 360]],
            ['thumbnails', 'thumbnail', 1, 'thumbnail-1.jpg', 'image/jpeg', 64, ['width' => 640, 'height' => 360]],
        ]);
        $video = $this->entity($media, Video::class);
        [$original, $h264, $first, $second] = $this->files($media);

        $this->assertSame([
            'id' => (string) $media->getKey(),
            'type' => 'video',
            'name' => 'Clip',
            'url' => 'https://cdn.test/storage/medias/clip.mov',
            'thumbnail_url' => 'https://cdn.test/storage/medias/thumbnail-0.jpg',
            'metadata' => [
                'filename' => 'clip.mov',
                'size' => 4096,
                'mime' => 'video/quicktime',
                'duration' => 12.5,
                'width' => 1920,
                'height' => 1080,
            ],
            'files' => [
                $this->legacyFile($original, 'original', 'clip.mov', 'video/quicktime', 4096),
                $this->legacyFile($h264, 'h264', 'h264.mp4', 'video/mp4', 2048),
                $this->legacyFile($first, 'thumbnails.0', 'thumbnail-0.jpg', 'image/jpeg', 64),
                $this->legacyFile($second, 'thumbnails.1', 'thumbnail-1.jpg', 'image/jpeg', 64),
            ],
        ], $this->render((new MediaResource($video))->legacy()));
    }

    public function test_the_legacy_shape_of_an_audio_is_the_one_of_1_1()
    {
        $media = $this->makeMedia('audio', 'Song', [
            ['original', 'original', null, 'song.wav', 'audio/wav', 8192, ['duration' => 180]],
            ['mp3', 'main', null, 'song.mp3', 'audio/mpeg', 1024, ['duration' => 180]],
        ]);
        $audio = $this->entity($media, Audio::class);
        [$original, $mp3] = $this->files($media);

        $this->assertSame([
            'id' => (string) $media->getKey(),
            'type' => 'audio',
            'name' => 'Song',
            'url' => 'https://cdn.test/storage/medias/song.wav',
            'thumbnail_url' => null,
            'metadata' => [
                'filename' => 'song.wav',
                'size' => 8192,
                'mime' => 'audio/wav',
                'duration' => 180, // a float without a fraction, which JSON writes as an integer
            ],
            'files' => [
                $this->legacyFile($original, 'original', 'song.wav', 'audio/wav', 8192),
                $this->legacyFile($mp3, 'mp3', 'song.mp3', 'audio/mpeg', 1024),
            ],
        ], $this->render((new MediaResource($audio))->legacy()));
    }

    public function test_the_legacy_shape_of_a_document_is_the_one_of_1_1()
    {
        $media = $this->makeMedia('document', 'Report', [
            ['original', 'original', null, 'report.pdf', 'application/pdf', 16384, ['metadata' => ['pages_count' => 12]]],
            ['thumbnails', 'thumbnail', 0, 'report-thumbnails-0.jpg', 'image/jpeg', 128, ['width' => 600, 'height' => 800]],
        ]);
        $document = $this->entity($media, Document::class);
        [$original, $thumbnail] = $this->files($media);

        $this->assertSame([
            'id' => (string) $media->getKey(),
            'type' => 'document',
            'name' => 'Report',
            'url' => 'https://cdn.test/storage/medias/report.pdf',
            'thumbnail_url' => 'https://cdn.test/storage/medias/report-thumbnails-0.jpg',
            'metadata' => [
                'filename' => 'report.pdf',
                'size' => 16384,
                'mime' => 'application/pdf',
                'pagesCount' => 12,
            ],
            'files' => [
                $this->legacyFile($original, 'original', 'report.pdf', 'application/pdf', 16384),
                $this->legacyFile($thumbnail, 'thumbnails.0', 'report-thumbnails-0.jpg', 'image/jpeg', 128),
            ],
        ], $this->render((new MediaResource($document))->legacy()));
    }

    public function test_the_legacy_shape_keeps_the_options_of_1_1()
    {
        $media = $this->makeMedia('image', 'Photo', [
            ['original', 'original', null, 'photo.jpg', 'image/jpeg', 1024, ['width' => 2000, 'height' => 1000]],
        ]);
        $image = $this->entity($media, Image::class);

        $data = $this->render((new MediaResource($image))->legacy()->withoutMetadata()->withoutImageSizes()->withFilesAsMap());
        $this->assertSame(['id', 'type', 'name', 'url', 'thumbnail_url', 'files'], array_keys($data));
        $this->assertSame(['original'], array_keys($data['files']));

        $data = $this->render((new MediaResource($image))->legacy()->withoutFiles());
        $this->assertArrayNotHasKey('files', $data);
    }

    public function test_the_shape_is_legacy_by_default()
    {
        $media = $this->makeMedia('image', 'Photo', [
            ['original', 'original', null, 'photo.jpg', 'image/jpeg', 1024, ['width' => 2000, 'height' => 1000]],
        ]);
        $image = $this->entity($media, Image::class);

        $this->assertSame($this->render((new MediaResource($image))->legacy()), $this->render(new MediaResource($image)));
        $this->assertSame((string) $media->getKey(), $this->render(new MediaResource($image))['id']);
    }

    public function test_the_canonical_shape_is_the_mediatheque_one()
    {
        $media = $this->makeMedia('image', 'Photo', [
            ['original', 'original', null, 'photo.jpg', 'image/jpeg', 1024, ['width' => 2000, 'height' => 1000]],
        ]);
        $image = $this->entity($media, Image::class);

        $data = $this->render((new MediaResource($image))->canonical());

        $this->assertSame($this->render(new MediathequeMediaResource($media)), $data);
        $this->assertSame($media->uid(), $data['id']);
        $this->assertSame(['original'], array_keys($data['files']));
    }

    public function test_the_canonical_shape_can_leave_out_files_and_metadata()
    {
        $media = $this->makeMedia('image', 'Photo', [
            ['original', 'original', null, 'photo.jpg', 'image/jpeg', 1024, ['width' => 2000, 'height' => 1000]],
        ]);

        $data = $this->render((new MediaResource($media))->canonical()->withoutFiles()->withoutMetadata());

        $this->assertArrayNotHasKey('files', $data);
        $this->assertArrayNotHasKey('metadata', $data);
        $this->assertSame($media->uid(), $data['id']);
    }

    public function test_the_config_sets_the_shape()
    {
        $media = $this->makeMedia('image', 'Photo', [
            ['original', 'original', null, 'photo.jpg', 'image/jpeg', 1024, ['width' => 2000, 'height' => 1000]],
        ]);
        config()->set('site.medias.resource', MediaResource::CANONICAL);

        $this->assertSame($media->uid(), $this->render(new MediaResource($media))['id']);
        $this->assertSame((string) $media->getKey(), $this->render((new MediaResource($media))->legacy())['id']);
    }

    public function test_a_model_gives_the_legacy_shape_of_its_entity()
    {
        $media = $this->makeMedia('image', 'Photo', [
            ['original', 'original', null, 'photo.jpg', 'image/jpeg', 1024, ['width' => 2000, 'height' => 1000]],
        ]);
        $image = $this->entity($media, Image::class);

        $this->assertSame(
            $this->render((new MediaResource($image))->legacy()),
            $this->render((new MediaResource($media))->legacy()),
        );
        $this->assertInstanceOf(Image::class, (new MediaResource($media))->entity());
    }

    public function test_a_collection_can_set_the_shape()
    {
        $media = $this->makeMedia('image', 'Photo', [
            ['original', 'original', null, 'photo.jpg', 'image/jpeg', 1024, ['width' => 2000, 'height' => 1000]],
        ]);
        $image = $this->entity($media, Image::class);
        $render = fn (MediasCollection $collection) => json_decode((string) json_encode($collection->toArray(Request::create('/'))), true);

        $this->assertSame($media->uid(), $render((new MediasCollection(collect([$image])))->legacy(false))[0]['id']);
        config()->set('site.medias.resource', MediaResource::CANONICAL);
        $this->assertSame((string) $media->getKey(), $render((new MediasCollection(collect([$image])))->legacy())[0]['id']);
    }

    public function test_a_media_without_an_original_plays_its_main_rendition()
    {
        $media = $this->makeMedia('video', 'Clip', [
            ['h264', 'main', null, 'h264.mp4', 'video/mp4', 2048, ['width' => 1920, 'height' => 1080, 'duration' => 12.5]],
            ['dash', 'derivative', null, 'clip.mpd', 'application/dash+xml', 128, []],
        ]);

        $data = $this->render(new MediaResource($this->entity($media, Video::class)));

        $this->assertSame('https://cdn.test/storage/medias/h264.mp4', $data['url']);
        $this->assertSame(['dash', 'h264'], array_column($data['files'], 'handle'));
    }

    public function test_an_entity_without_a_media_model_is_refused()
    {
        $entity = new class implements MediaEntity
        {
            public function id(): string
            {
                return '1';
            }

            public function url(): string
            {
                return '';
            }

            public function metadata(): MediaMetadata
            {
                throw new \RuntimeException('Not used');
            }

            public function thumbnailUrl(): ?string
            {
                return null;
            }

            public function files(): Collection
            {
                return collect();
            }
        };

        $this->expectException(InvalidArgumentException::class);

        new MediaResource($entity);
    }

    /**
     * @param  list<array{string, string, int|null, string, string, int, array<string, mixed>}>  $files
     */
    private function makeMedia(string $type, string $name, array $files): Media
    {
        $media = new Media;
        $media->forceFill(['type' => $type, 'name' => $name, 'status' => 'ready', 'visibility' => 'public'])->save();
        foreach ($files as [$handle, $role, $index, $path, $mime, $size, $attributes]) {
            (new File)->forceFill([
                'media_id' => $media->getKey(),
                'handle' => $handle,
                'role' => $role,
                'index' => $index,
                'source' => 'public',
                'path' => 'medias/'.$path,
                'mime' => $mime,
                'size' => $size,
                'checksum' => hash('sha256', $path),
                ...$attributes,
            ])->save();
        }

        $fresh = $media->fresh('mediaFiles');
        $this->assertInstanceOf(Media::class, $fresh);

        return $fresh;
    }

    /**
     * @template T of MediaEntity
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function entity(Media $media, string $class): MediaEntity
    {
        $entity = $media->toEntity();
        $this->assertInstanceOf($class, $entity);

        return $entity;
    }

    /**
     * The files of a media in the order they were created.
     *
     * @return list<File>
     */
    private function files(Media $media): array
    {
        return File::query()->where('media_id', $media->getKey())->orderBy('id')->get()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyFile(File $file, string $handle, string $name, string $mime, int $size): array
    {
        return [
            'id' => (string) $file->getKey(),
            'handle' => $handle,
            'name' => $name,
            'url' => 'https://cdn.test/storage/'.$file->path(),
            'mime' => $mime,
            'size' => $size,
        ];
    }

    /**
     * The resource as a front end reads it.
     *
     * @return array<string, mixed>
     */
    private function render(MediathequeMediaResource $resource): array
    {
        $data = json_decode((string) json_encode($resource), true);
        $this->assertIsArray($data);

        return $data;
    }
}
