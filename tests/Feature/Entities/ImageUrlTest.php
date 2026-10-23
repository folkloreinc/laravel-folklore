<?php

namespace Folklore\Tests\Feature\Entities;

use Folklore\Entities\Image;
use Folklore\Entities\ImageSize;
use Folklore\Image\Facades\Image as ImageFacade;
use Folklore\Mediatheque\Models\File;
use Folklore\Models\Media;
use Folklore\Tests\TestCase;

/**
 * The sizes of an image are laravel-image 2's presets, rendered from the media.
 */
class ImageUrlTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('image.strict', false);
        $app['config']->set('image.presets', [
            'thumbnail' => ['width' => 200, 'height' => 200],
            'card' => ['width' => 800, 'format' => 'jpeg'],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateMediatheque();
    }

    public function test_an_image_without_filters_keeps_its_original_url()
    {
        $image = $this->makeImage();

        $this->assertSame($image->urlWithoutFilters(), $image->url());
        $this->assertStringEndsWith('/photo.jpg', $image->url());
    }

    public function test_an_image_has_a_size_per_preset()
    {
        $image = $this->makeImage();

        $this->assertSame(['thumbnail', 'card'], $image->sizes()->map(fn (ImageSize $size) => $size->id())->all());
    }

    public function test_a_size_is_the_variant_of_its_preset()
    {
        $image = $this->makeImage();
        $card = $image->sizes()->firstWhere(fn (ImageSize $size) => $size->id() === 'card');

        $this->assertSame(ImageFacade::for($image->getModel())->preset('card')->url(), $card->url());
        $this->assertSame(800, $card->width());
        $this->assertSame(400, $card->height());
        $this->assertSame('image/jpeg', $card->mime());
    }

    public function test_a_size_can_be_in_another_format()
    {
        $image = $this->makeImage();
        $size = new ImageSize($image, 'card', 'webp');

        $this->assertSame(ImageFacade::for($image->getModel())->preset('card')->webp()->url(), $size->url());
        $this->assertSame('image/webp', $size->mime());
    }

    public function test_a_filter_with_a_preset_uses_its_url()
    {
        $image = $this->makeImage();

        $this->assertSame(
            ImageFacade::for($image->getModel())->preset('thumbnail')->url(),
            $image->setFilters('thumbnail')->url(),
        );
        $this->assertStringEndsWith('/photo.jpg', $image->urlWithoutFilters());
    }

    public function test_filters_with_a_width_and_a_format_use_laravel_image()
    {
        $image = $this->makeImage();

        $this->assertSame(
            ImageFacade::for($image->getModel())->width(400)->format('webp')->url(),
            $image->setFilters(['width' => 400, 'format' => 'webp', 'colorize' => 'red'])->url(),
        );
    }

    public function test_a_size_of_an_image_without_dimensions_falls_back_to_the_original()
    {
        $image = $this->makeImage(width: null, height: null);
        $size = new ImageSize($image, 'card');

        $this->assertSame($image->urlWithoutFilters(), $size->url());
        $this->assertSame(0, $size->width());
        $this->assertSame('image/jpeg', $size->mime());
    }

    public function test_a_size_of_an_unknown_preset_falls_back_to_the_original()
    {
        $image = $this->makeImage();
        $size = new ImageSize($image, 'missing');

        $this->assertSame($image->urlWithoutFilters(), $size->url());
        $this->assertSame(2000, $size->width());
    }

    private function makeImage(?int $width = 2000, ?int $height = 1000): Image
    {
        $media = new Media;
        $media->forceFill(['type' => 'image', 'name' => 'Photo', 'status' => 'ready', 'visibility' => 'public'])->save();
        $file = new File;
        $file->forceFill([
            'media_id' => $media->id,
            'handle' => 'original',
            'role' => 'original',
            'source' => 'public',
            'path' => 'medias/photo.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'checksum' => hash('sha256', 'photo'),
            'width' => $width,
            'height' => $height,
        ])->save();

        $image = $media->fresh('mediaFiles')?->toEntity();
        $this->assertInstanceOf(Image::class, $image);

        return $image;
    }
}
