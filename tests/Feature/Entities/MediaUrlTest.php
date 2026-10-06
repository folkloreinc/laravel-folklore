<?php

namespace Folklore\Tests\Feature\Entities;

use Folklore\Contracts\Entities\Image as ImageContract;
use Folklore\Contracts\Entities\MediaFile as MediaFileContract;
use Folklore\Entities\Image as ImageEntity;
use Folklore\Entities\ImageSize;
use Folklore\Entities\Media as MediaEntity;
use Folklore\Models\Media as MediaModel;
use Folklore\Tests\TestCase;
use Illuminate\Support\Collection;

class MediaUrlTest extends TestCase
{
    public function test_it_uses_the_original_file()
    {
        $media = $this->makeMedia([
            $this->makeFile('thumbnail', 'https://example.com/thumbnail.jpg'),
            $this->makeFile('original', 'https://example.com/original.jpg'),
        ]);

        $this->assertSame('https://example.com/original.jpg', $media->url());
    }

    public function test_it_falls_back_to_the_first_file_without_an_original()
    {
        $media = $this->makeMedia([
            $this->makeFile('thumbnail', 'https://example.com/thumbnail.jpg'),
            $this->makeFile('large', 'https://example.com/large.jpg'),
        ]);

        $this->assertSame('https://example.com/thumbnail.jpg', $media->url());
    }

    public function test_it_returns_an_empty_url_without_files()
    {
        $media = $this->makeMedia([]);

        $this->assertSame('', $media->url());
    }

    public function test_an_image_with_filters_returns_an_empty_url_without_files()
    {
        $image = new class(new MediaModel) extends ImageEntity
        {
            public function files(): Collection
            {
                return collect();
            }
        };

        $this->assertSame('', $image->setFilters(['large'])->url());
        $this->assertSame('', $image->urlWithoutFilters());
    }

    public function test_an_image_size_returns_an_empty_url_without_files()
    {
        $image = $this->createStub(ImageContract::class);
        $image->method('urlWithoutFilters')->willReturn('');

        $size = new ImageSize($image, ['id' => 'large', 'maxWidth' => 1000]);

        $this->assertSame('', $size->url());
    }

    protected function makeMedia(array $files): MediaEntity
    {
        return new class(new MediaModel, collect($files)) extends MediaEntity
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

    protected function makeFile(string $handle, string $url): MediaFileContract
    {
        $file = $this->createStub(MediaFileContract::class);
        $file->method('handle')->willReturn($handle);
        $file->method('url')->willReturn($url);

        return $file;
    }
}
