<?php

namespace Folklore\Tests\Feature\Eloquent;

use Folklore\Eloquent\MediaUsagesSync;
use Folklore\Tests\Feature\Eloquent\Fixtures\Game;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The media of JSON data go into laravel-mediatheque's usage graph under the `json` handle
 * (docs/json-data.md, "JSON data"), and `json-data:sync-references` compares them through
 * MediaUsagesSync::synced().
 */
class MediaUsagesSyncTest extends JsonDataTestCase
{
    public function test_synced_gives_the_media_of_the_json_handle_only()
    {
        $image = $this->makeMedia();
        $cover = $this->makeMedia();
        $game = new Game;
        $game->data = ['image' => ['fr' => $image]];
        $game->save();
        $game->attachMedia($cover, 'cover');

        $sync = new MediaUsagesSync;

        $this->assertSame([$image->id], $sync->synced($game, 'medias', $game->medias()));
        $this->assertNull($sync->synced($game, 'blocks', $game->blocks()));
        $this->assertNull($sync->synced(new class extends Model {}, 'medias', $game->medias()));
    }

    public function test_the_sync_command_compares_the_media_of_the_json_handle()
    {
        $image = $this->makeMedia();
        $cover = $this->makeMedia();
        $id = DB::table('games')->insertGetId(['handle' => 'quiz', 'data' => json_encode(['image' => ['fr' => 'medias://'.$image->id]])]);
        $game = Game::findOrFail($id);
        $game->attachMedia($cover, 'cover');
        $headers = ['Model', 'Rows', 'Rows to change', 'To attach', 'To detach', 'Synced by callbacks, not compared', 'Unresolved references'];

        $this->artisan('json-data:sync-references', ['--model' => [Game::class], '--dry-run' => true])
            ->expectsTable($headers, [[Game::class, 1, 1, 1, 0, 0, 0]])
            ->assertSuccessful();

        $this->artisan('json-data:sync-references', ['--model' => [Game::class]])->assertSuccessful();
        $this->assertSame([$image->id], $game->fresh()?->medias(MediaUsagesSync::HANDLE)->map->getKey()->all());
        $this->assertSame([$cover->id], $game->fresh()?->medias('cover')->map->getKey()->all());

        $this->artisan('json-data:sync-references', ['--model' => [Game::class], '--dry-run' => true])
            ->expectsTable($headers, [[Game::class, 1, 0, 0, 0, 0, 0]])
            ->assertSuccessful();
    }
}
