<?php

namespace Folklore\Tests\Feature;

use Folklore\Eloquent\JsonDataCast;
use Folklore\Tests\TestCase;

class PostsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'testbench']);
        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--path' => __DIR__ . '/../migrations',
            '--realpath' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $filesPath = public_path('files');
        if (app('files')->exists($filesPath)) {
            app('files')->deleteDirectory($filesPath);
        }

        parent::tearDown();
    }

    public function testCreatePost()
    {
        $media = media(public_path('folklore.png'));
        $media->save();
        $post = new Post();
        $post->data = [
            'image' => $media,
            'images' => [$media, $media],
        ];
        $post->save();
        JsonDataCast::syncRelations($post);

        $post = Post::find($post->id);
        $rawData = json_decode($post->getRawOriginal('data'), true);
        $this->assertEquals($post->medias->first()->id, $media->id);
        $this->assertEquals($post->data['image']->id, $media->id);
        $this->assertEquals(data_get($rawData, 'image'), 'medias://' . $media->id);
        $this->assertEquals(data_get($rawData, 'images'), ['medias://' . $media->id, 'medias://' . $media->id]);

        $post->data = [];
        $post->save();
        JsonDataCast::syncRelations($post);

        $post = Post::find($post->id);
        $rawData = json_decode($post->getRawOriginal('data'), true);
        $this->assertNull($post->medias->first());
        $this->assertNull(data_get($post->data, 'image'));
        $this->assertNull(data_get($rawData, 'image'));
        $this->assertNull(data_get($rawData, 'images'));
    }
}
