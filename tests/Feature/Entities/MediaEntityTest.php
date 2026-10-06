<?php

namespace Folklore\Tests\Feature\Entities;

use Folklore\Entities\Media as MediaEntity;
use Folklore\Models\Media as MediaModel;
use Folklore\Tests\TestCase;

class MediaEntityTest extends TestCase
{
    public function test_it_exposes_the_timestamps_of_the_model()
    {
        $model = new MediaModel;
        $model->forceFill([
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-03-04 05:06:07',
        ]);

        $media = new MediaEntity($model);

        $this->assertSame('2026-01-02 03:04:05', $media->createdAt()?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-04 05:06:07', $media->updatedAt()?->format('Y-m-d H:i:s'));
    }
}
