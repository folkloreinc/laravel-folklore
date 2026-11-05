<?php

namespace Folklore\Tests\Feature\Entities;

use Folklore\Entities\Image;
use Folklore\Entities\ImageSize;
use Folklore\Http\Resources\ImageSizeResource;
use Folklore\Image\Facades\Image as ImageFacade;
use Folklore\Image\Urls\V1Codec;
use Folklore\Mediatheque\Models\File;
use Folklore\Models\Media;
use Folklore\Tests\TestCase;
use Illuminate\Http\Request;

/**
 * With `site.medias.image_urls` set to `legacy`, image sizes keep the 1.x URLs and the
 * dimensions that 1.1 gave them, from `image.sizes` (#162).
 */
class ImageLegacyUrlTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('site.medias.image_urls', 'legacy');
        $app['config']->set('app.image_url', 'https://images.example.com/');
        $app['config']->set('image.strict', false);
        // A 1.x config: laravel-image 2 reads the sizes as presets, as `image.presets` isn't set.
        $app['config']->set('image.sizes', [
            ['id' => 'thumbnail', 'width' => 200, 'height' => 200],
            ['id' => 'large', 'maxWidth' => 1400, 'maxHeight' => 1400],
            ['id' => 'medium', 'maxWidth' => 800],
            ['id' => 'tall', 'maxHeight' => 300, 'format' => 'webp'],
            ['id' => 'original'],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateMediatheque();
    }

    public function test_an_image_has_the_sizes_of_image_sizes_in_their_order()
    {
        $image = $this->makeImage();

        $this->assertSame(
            ['thumbnail', 'large', 'medium', 'tall', 'original'],
            $image->sizes()->map(fn (ImageSize $size) => $size->id())->all(),
        );
    }

    public function test_a_size_has_its_1x_url_on_the_image_host()
    {
        $image = $this->makeImage();

        $this->assertSame($this->legacyUrl($image, '-filters(thumbnail).jpg'), $this->sizeOf($image, 'thumbnail')->url());
        $this->assertSame($this->legacyUrl($image, '-filters(large).jpg'), $this->sizeOf($image, 'large')->url());
        $this->assertStringStartsWith('https://images.example.com/', $this->sizeOf($image, 'large')->url());
    }

    public function test_a_size_has_the_dimensions_of_1_1()
    {
        $image = $this->makeImage();

        $this->assertSame([200, 200], $this->dimensions($this->sizeOf($image, 'thumbnail')));
        $this->assertSame([1400, 700], $this->dimensions($this->sizeOf($image, 'large')));
        $this->assertSame([800, 400], $this->dimensions($this->sizeOf($image, 'medium')));
        $this->assertSame([600, 300], $this->dimensions($this->sizeOf($image, 'tall')));
        $this->assertSame([2000, 1000], $this->dimensions($this->sizeOf($image, 'original')));
    }

    public function test_a_size_never_exceeds_the_original_but_its_fixed_dimensions()
    {
        $image = $this->makeImage(width: 600, height: 400);

        $this->assertSame([600, 400], $this->dimensions($this->sizeOf($image, 'large')));
        $this->assertSame([600, 400], $this->dimensions($this->sizeOf($image, 'medium')));
        $this->assertSame([200, 200], $this->dimensions($this->sizeOf($image, 'thumbnail')));
    }

    public function test_the_legacy_route_decodes_the_urls()
    {
        $image = $this->makeImage();
        $path = (string) parse_url((new ImageSize($image, 'large', 'webp'))->url(), PHP_URL_PATH);

        $url = app(V1Codec::class)->decode(ltrim($path, '/'));

        $this->assertStringEndsWith('photo.jpg', $url->key);
        $this->assertSame('webp', $url->format->extension());
        $this->assertNotNull($url->spec);
    }

    public function test_the_original_size_keeps_the_original_url()
    {
        $image = $this->makeImage();
        $original = $this->sizeOf($image, 'original');

        $this->assertSame($image->urlWithoutFilters(), $original->url());
        $this->assertSame('image/jpeg', $original->mime());
    }

    public function test_a_format_is_a_format_extension()
    {
        $image = $this->makeImage();
        $size = new ImageSize($image, 'large', 'webp');

        $this->assertSame($this->legacyUrl($image, '-filters(large).jpg.webp'), $size->url());
        $this->assertSame('image/webp', $size->mime());
        $this->assertSame([1400, 700], $this->dimensions($size));

        $tall = $this->sizeOf($image, 'tall');
        $this->assertSame($this->legacyUrl($image, '-filters(tall).jpg.webp'), $tall->url());
        $this->assertSame('image/webp', $tall->mime());

        $this->assertSame($this->legacyUrl($image, '-filters(thumbnail).jpg'), (new ImageSize($image, 'thumbnail', 'jpg'))->url());
    }

    public function test_an_svg_keeps_its_url()
    {
        $image = $this->makeImage(path: 'medias/logo.svg', mime: 'image/svg+xml');

        $this->assertSame($image->urlWithoutFilters(), $this->sizeOf($image, 'large')->url());
        $this->assertSame('image/svg+xml', $this->sizeOf($image, 'large')->mime());
    }

    public function test_an_image_without_files_has_no_url()
    {
        $media = new Media;
        $media->forceFill(['type' => 'image', 'name' => 'Empty', 'status' => 'ready', 'visibility' => 'public'])->save();
        $image = $media->fresh('mediaFiles')?->toEntity();
        $this->assertInstanceOf(Image::class, $image);

        $this->assertSame('', $this->sizeOf($image, 'large')->url());
    }

    public function test_the_resource_of_a_size_is_the_one_of_1_1()
    {
        $image = $this->makeImage();

        $this->assertSame([
            'id' => 'large',
            'url' => $this->legacyUrl($image, '-filters(large).jpg'),
            'width' => 1400,
            'height' => 700,
            'mime' => 'image/jpeg',
        ], (new ImageSizeResource($this->sizeOf($image, 'large')))->toArray(new Request));
    }

    public function test_without_image_sizes_the_sizes_are_the_presets_with_1x_urls()
    {
        config()->set('image.sizes', null);
        config()->set('image.presets', ['card' => ['width' => 800, 'format' => 'jpeg']]);
        $image = $this->makeImage();
        $card = $this->sizeOf($image, 'card');

        $this->assertSame(['card'], $image->sizes()->map(fn (ImageSize $size) => $size->id())->all());
        $this->assertSame($this->legacyUrl($image, '-filters(card).jpg'), $card->url());
        $variant = ImageFacade::for($image->getModel())->preset('card')->variant();
        $this->assertSame([$variant->width, $variant->height], $this->dimensions($card));
    }

    public function test_the_presets_mode_gives_the_2x_urls()
    {
        config()->set('site.medias.image_urls', 'presets');
        $image = $this->makeImage();
        $large = $this->sizeOf($image, 'large');

        $this->assertSame(ImageFacade::for($image->getModel())->preset('large')->url(), $large->url());
        $this->assertStringNotContainsString('-filters(', $large->url());
        $this->assertNotContains('original', $image->sizes()->map(fn (ImageSize $size) => $size->id())->all());
    }

    private function sizeOf(Image $image, string $id): ImageSize
    {
        $size = $image->sizes()->firstWhere(fn (ImageSize $size) => $size->id() === $id);
        $this->assertInstanceOf(ImageSize::class, $size);

        return $size;
    }

    /**
     * @return array{int, int}
     */
    private function dimensions(ImageSize $size): array
    {
        return [$size->width(), $size->height()];
    }

    /**
     * The 1.x URL of the image's original, without its extension, with a suffix.
     */
    private function legacyUrl(Image $image, string $suffix): string
    {
        $path = (string) parse_url($image->urlWithoutFilters(), PHP_URL_PATH);

        return 'https://images.example.com'.preg_replace('/\.\w+$/', '', $path).$suffix;
    }

    private function makeImage(int $width = 2000, int $height = 1000, string $path = 'medias/photo.jpg', string $mime = 'image/jpeg'): Image
    {
        $media = new Media;
        $media->forceFill(['type' => 'image', 'name' => 'Photo', 'status' => 'ready', 'visibility' => 'public'])->save();
        $file = new File;
        $file->forceFill([
            'media_id' => $media->id,
            'handle' => 'original',
            'role' => 'original',
            'source' => 'public',
            'path' => $path,
            'mime' => $mime,
            'size' => 1024,
            'checksum' => hash('sha256', $path),
            'width' => $width,
            'height' => $height,
        ])->save();

        $image = $media->fresh('mediaFiles')?->toEntity();
        $this->assertInstanceOf(Image::class, $image);

        return $image;
    }
}
