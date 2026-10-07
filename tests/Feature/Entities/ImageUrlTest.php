<?php

namespace Folklore\Tests\Feature\Entities;

use Folklore\Contracts\Entities\Image as ImageContract;
use Folklore\Contracts\Entities\ImageMetadata as ImageMetadataContract;
use Folklore\Contracts\Entities\MediaFile as MediaFileContract;
use Folklore\Entities\Image as ImageEntity;
use Folklore\Entities\ImageSize;
use Folklore\Image\ServiceProvider as ImageServiceProvider;
use Folklore\Models\Media as MediaModel;
use Folklore\Tests\TestCase;
use Illuminate\Support\Collection;

class ImageUrlTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.image_url', 'https://images.example.com/');
    }

    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [ImageServiceProvider::class]);
    }

    public function test_an_image_with_filters_uses_the_image_url_format()
    {
        $image = $this->makeImage('https://example.com/uploads/photo.jpg');

        $this->assertSame(
            'https://images.example.com/uploads/photo-filters(large).jpg',
            $image->setFilters(['large'])->url()
        );
        $this->assertSame('https://example.com/uploads/photo.jpg', $image->urlWithoutFilters());
    }

    public function test_an_image_without_filters_keeps_its_original_url()
    {
        $image = $this->makeImage('https://example.com/uploads/photo.jpg');

        $this->assertSame('https://example.com/uploads/photo.jpg', $image->url());
    }

    public function test_an_image_size_adds_its_filter()
    {
        $size = new ImageSize(
            $this->makeImageStub('https://example.com/uploads/photo.jpg', 'image/jpeg'),
            ['id' => 'large', 'maxWidth' => 1000]
        );

        $this->assertSame('https://images.example.com/uploads/photo-filters(large).jpg', $size->url());
    }

    public function test_an_image_size_converts_to_its_format()
    {
        $size = new ImageSize(
            $this->makeImageStub('https://example.com/uploads/photo.jpg', 'image/jpeg'),
            ['id' => 'large', 'maxWidth' => 1000],
            'webp',
            ['grayscale']
        );

        $this->assertSame(
            'https://images.example.com/uploads/photo-filters(grayscale-large).jpg.webp',
            $size->url()
        );
    }

    public function test_an_original_image_size_without_filters_keeps_its_url()
    {
        $size = new ImageSize(
            $this->makeImageStub('https://example.com/uploads/photo.jpg', 'image/jpeg'),
            ['id' => 'original']
        );

        $this->assertSame('https://example.com/uploads/photo.jpg', $size->url());
    }

    public function test_an_svg_image_size_keeps_its_url()
    {
        $size = new ImageSize(
            $this->makeImageStub('https://example.com/uploads/logo.svg', 'image/svg+xml'),
            ['id' => 'large', 'maxWidth' => 1000],
            'webp'
        );

        $this->assertSame('https://example.com/uploads/logo.svg', $size->url());
    }

    protected function makeImage(string $url): ImageEntity
    {
        $file = $this->createStub(MediaFileContract::class);
        $file->method('handle')->willReturn('original');
        $file->method('url')->willReturn($url);

        return new class(new MediaModel, collect([$file])) extends ImageEntity
        {
            public function __construct(MediaModel $model, protected Collection $testFiles)
            {
                parent::__construct($model);
            }

            public function files(): Collection
            {
                return $this->testFiles;
            }
        };
    }

    protected function makeImageStub(string $url, string $mime): ImageContract
    {
        $metadata = $this->createStub(ImageMetadataContract::class);
        $metadata->method('mime')->willReturn($mime);

        $image = $this->createStub(ImageContract::class);
        $image->method('urlWithoutFilters')->willReturn($url);
        $image->method('metadata')->willReturn($metadata);

        return $image;
    }
}
