<?php

namespace Folklore\Tests\Feature\Repositories;

use Folklore\Mediatheque\Jobs\ProbeMedia;
use Folklore\Models\Media;
use Folklore\Tests\Feature\Eloquent\Fixtures\Game;
use Folklore\Tests\Feature\Eloquent\Fixtures\Games;
use Folklore\Tests\Feature\Eloquent\JsonDataTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Repositories save in a database transaction, so that a save that fails after the model's
 * `saving` event rolls back the media its JSON references ingested (docs/decisions/0013).
 */
class SaveTransactionTest extends JsonDataTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Bus::fake([ProbeMedia::class]);
    }

    public function test_a_failed_save_rolls_back_the_media_it_ingested()
    {
        Game::saved(function () {
            throw new RuntimeException('The save failed after the saving event.');
        });

        try {
            (new Games)->create(['image' => ['fr' => UploadedFile::fake()->image('cover.png', 40, 30)]]);
            $this->fail('The save did not fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The save failed after the saving event.', $exception->getMessage());
        }

        $this->assertSame(0, Media::query()->count());
        $this->assertSame(0, Game::query()->count());
        Bus::assertNotDispatched(ProbeMedia::class);
    }

    public function test_a_save_that_succeeds_keeps_its_media()
    {
        $game = (new Games)->create(['image' => ['fr' => UploadedFile::fake()->image('cover.png', 40, 30)]]);

        $this->assertSame(1, Media::query()->count());
        $this->assertNotNull($game->id());
        Bus::assertDispatched(ProbeMedia::class);
    }
}
