<?php

namespace Folklore\Tests\Feature\Eloquent;

use Folklore\Eloquent\Exceptions\InvalidJsonReference;
use Folklore\Eloquent\Exceptions\ReferenceNotAllowed;
use Folklore\Eloquent\JsonData;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Eloquent\MediaReferences;
use Folklore\Mediatheque\Contracts\Models\Media as MediaContract;
use Folklore\Mediatheque\Jobs\ProbeMedia;
use Folklore\Models\Media;
use Folklore\Repositories\Medias;
use Folklore\Tests\Feature\Eloquent\Fixtures\Game;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * The media handler of the medias module, for the `media` and `medias` keys
 * (docs/json-data.md, "Media references", docs/decisions/0008).
 */
class MediaReferencesTest extends JsonDataTestCase
{
    private string $referenceKey = 'medias';

    private bool $otherKey = true;

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('site.medias.reference_key', $this->referenceKey);
        $app['config']->set('site.medias.references.other_key', $this->otherKey);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Bus::fake([ProbeMedia::class]);
        Schema::table('mediatheque_medias', function (Blueprint $table) {
            $table->string('workspace')->nullable();
        });
    }

    private function useKey(string $key): void
    {
        $this->referenceKey = $key;
        $this->refreshApplication();
        $this->setUp();
    }

    public function test_the_medias_module_maps_both_keys_in_the_order_of_its_setting()
    {
        $this->assertSame(['medias', 'media'], JsonData::keysFor(Media::class));
        $this->assertSame('uid', JsonData::target('media')?->column());
        $this->assertSame(MediaReferences::class, JsonData::target('medias')?->handler);

        $this->useKey('media');

        $this->assertSame(['media', 'medias'], JsonData::keysFor(Media::class));
    }

    public function test_a_new_project_writes_media_references_by_uid()
    {
        $this->useKey('media');
        $media = $this->makeMedia();

        $game = new Game;
        $game->data = ['image' => ['fr' => $media]];
        $game->save();

        $this->assertSame('media://'.$media->uid, $this->rawData($game)['image']['fr']);
        $this->assertTrue(Game::find($game->id)?->data['image']['fr']->is($media));
    }

    public function test_an_upgraded_site_writes_media_references_by_id()
    {
        $media = $this->makeMedia();

        $game = new Game;
        $game->data = ['image' => ['fr' => $media]];
        $game->save();

        $this->assertSame('medias://'.$media->id, $this->rawData($game)['image']['fr']);
    }

    public function test_both_keys_are_read_and_synced()
    {
        $byUid = $this->makeMedia();
        $byId = $this->makeMedia();
        $game = Game::create(['handle' => 'quiz']);
        $game->forceFill(['data' => null])->save();
        Game::query()->whereKey($game->id)->update(['data' => json_encode(['image' => [
            'fr' => 'media://'.$byUid->uid,
            'en' => 'medias://'.$byId->id,
        ]])]);

        $game = Game::find($game->id);
        $this->assertTrue($game?->data['image']['fr']->is($byUid));
        $this->assertTrue($game?->data['image']['en']->is($byId));

        JsonDataCast::syncRelations($game);

        $this->assertEqualsCanonicalizing([$byUid->id, $byId->id], $game?->fresh()?->medias(JsonDataCast::MEDIAS_HANDLE)->map->getKey()->all());
    }

    public function test_it_prepares_what_forms_and_seeders_give()
    {
        $media = $this->makeMedia();
        $entity = $media->toEntity();
        $values = [
            'model' => $media,
            'entity' => $entity,
            'uid' => $media->uid,
            'id' => (string) $media->id,
            'legacy' => 'medias://'.$media->id,
            'panneau' => ['id' => $media->uid, 'name' => 'folklore'],
        ];

        $game = new Game;
        $game->data = ['image' => [...$values, 'reference' => 'media://'.$media->uid]];
        $game->save();

        foreach (array_keys($values) as $key) {
            $this->assertSame('medias://'.$media->id, $this->rawData($game)['image'][$key], $key);
        }
        // A reference of the other key is kept as it is: the rewrite command converts it
        $this->assertSame('media://'.$media->uid, $this->rawData($game)['image']['reference']);
    }

    public function test_a_value_that_isnt_a_media_is_written_as_null()
    {
        $game = new Game;
        $game->data = ['image' => ['uid' => 'med_01ABCDEFGHJKMNPQRSTVWXYZ00', 'id' => '999', 'text' => 'folklore']];
        $game->save();

        // MySQL's JSON type orders the keys of an object
        $this->assertEquals(['uid' => null, 'id' => null, 'text' => null], $this->rawData($game)['image']);
    }

    public function test_an_uploaded_file_is_ingested_when_the_owner_is_saved()
    {
        MediaReferences::ingestUsing(fn (Model $owner) => ['workspace' => 'ws-'.$owner->getAttribute('handle')]);

        $game = new Game(['handle' => 'quiz']);
        $game->data = ['image' => ['fr' => UploadedFile::fake()->image('cover.png', 40, 30)]];

        $this->assertSame(0, Media::query()->count());

        $game->save();

        $media = Media::query()->sole();
        $this->assertSame('medias://'.$media->id, $this->rawData($game)['image']['fr']);
        $this->assertSame('ws-quiz', $media->getAttribute('workspace'));
        Storage::disk('public')->assertExists((string) $media->original()?->path());
    }

    public function test_a_file_is_ingested()
    {
        $game = new Game;
        $game->data = ['image' => ['fr' => new File(public_path('folklore.png'))]];
        $game->save();

        $this->assertSame('medias://'.Media::query()->sole()->id, $this->rawData($game)['image']['fr']);
    }

    public function test_a_url_is_downloaded_with_the_repository_s_limits_then_ingested()
    {
        $this->app->instance(Medias::class, $this->mediasDownloading(new Response(200, [], (string) file_get_contents(public_path('folklore.png')))));
        $this->app['config']->set('site.medias.download.allowed_hosts', ['cdn.example.com']);

        $game = new Game;
        $game->data = ['image' => ['fr' => 'https://cdn.example.com/folklore.png', 'en' => 'https://elsewhere.example.org/folklore.png']];
        $game->save();

        $media = Media::query()->sole();
        $this->assertSame('medias://'.$media->id, $this->rawData($game)['image']['fr']);
        $this->assertNull($this->rawData($game)['image']['en']);
    }

    public function test_an_owner_may_be_refused_a_media()
    {
        MediaReferences::allowUsing(fn (MediaContract $media, ?Model $owner) => $media->name() !== 'private');
        $public = $this->makeMedia();
        $private = $this->makeMedia();
        $private->forceFill(['name' => 'private'])->save();

        $game = Game::create(['handle' => 'quiz']);
        Game::query()->whereKey($game->id)->update(['data' => json_encode(['image' => [
            'fr' => 'medias://'.$public->id,
            'en' => 'medias://'.$private->id,
        ]])]);
        $read = Game::find($game->id);
        $this->assertTrue($read?->data['image']['fr']->is($public));
        $this->assertNull($read?->data['image']['en']);

        $game = new Game;
        $game->data = ['image' => ['fr' => $private]];

        $this->expectException(ReferenceNotAllowed::class);

        $game->save();
    }

    public function test_every_form_is_accepted_by_default_without_enforce_map()
    {
        $this->assertSame(MediaReferences::FORMS, (new MediaReferences)->acceptedForms());

        JsonData::enforceMap();

        $this->assertSame(MediaReferences::STRICT, (new MediaReferences)->acceptedForms());
    }

    public function test_strict_forms_refuse_a_url_a_file_and_an_internal_id_under_enforce_map()
    {
        $this->useKey('media');
        JsonData::enforceMap();
        $media = $this->makeMedia();
        $this->app->instance(Medias::class, $this->mediasDownloading(new Response(500)));

        $game = new Game;
        $game->data = ['image' => ['model' => $media, 'entity' => $media->toEntity(), 'uid' => $media->uid, 'reference' => 'media://'.$media->uid, 'panneau' => ['id' => $media->uid]]];
        $game->save();

        // PostgreSQL's jsonb and MySQL's JSON type order the keys of an object
        $this->assertEquals(array_fill_keys(['model', 'entity', 'uid', 'reference', 'panneau'], 'media://'.$media->uid), $this->rawData($game)['image']);

        foreach ([
            'url' => 'https://cdn.example.com/folklore.png',
            'file' => new File(public_path('folklore.png')),
            'uploaded file' => UploadedFile::fake()->image('cover.png', 40, 30),
            'id' => (string) $media->id,
            'int' => $media->id,
            'panneau id' => ['id' => $media->id],
        ] as $form => $value) {
            $game = new Game;
            $game->data = ['image' => ['fr' => $value]];

            try {
                $game->save();
                $this->fail("The {$form} was accepted.");
            } catch (InvalidJsonReference $e) {
                $this->assertStringContainsString('site.medias.references.accept', $e->getMessage(), $form);
            }
        }

        // Nothing was downloaded nor ingested
        $this->assertSame(1, Media::query()->count());
    }

    public function test_a_form_that_isnt_accepted_is_written_as_null_without_enforce_map()
    {
        $this->app['config']->set('site.medias.references.accept', MediaReferences::STRICT);
        $media = $this->makeMedia();

        $game = new Game;
        $game->data = ['image' => ['fr' => $media->uid, 'en' => new File(public_path('folklore.png')), 'es' => (string) $media->id]];
        $game->save();

        $this->assertEquals(['fr' => 'medias://'.$media->id, 'en' => null, 'es' => null], $this->rawData($game)['image']);
        $this->assertSame(1, Media::query()->count());
    }

    public function test_the_accepted_forms_are_set_one_by_one()
    {
        $this->app['config']->set('site.medias.references.accept', ['uid', 'file']);
        $media = $this->makeMedia();

        $game = new Game;
        $game->data = ['image' => ['fr' => $media->uid, 'en' => new File(public_path('folklore.png')), 'es' => $media]];
        $game->save();

        $ingested = Media::query()->whereKeyNot($media->id)->sole();
        $this->assertEquals(['fr' => 'medias://'.$media->id, 'en' => 'medias://'.$ingested->id, 'es' => null], $this->rawData($game)['image']);
    }

    public function test_an_unknown_form_is_an_error()
    {
        $this->app['config']->set('site.medias.references.accept', ['uid', 'path']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[path]');

        (new MediaReferences)->acceptedForms();
    }

    public function test_the_medias_module_can_map_only_the_key_it_writes()
    {
        $this->otherKey = false;
        $this->useKey('media');

        $this->assertSame(['media'], JsonData::keysFor(Media::class));
        $this->assertNull(JsonData::target('medias'));
    }

    public function test_a_reference_of_the_other_key_is_refused_once_it_isnt_mapped()
    {
        $this->useKey('media');
        JsonData::enforceMap();
        $media = $this->makeMedia();

        // A reference of a mapped key is kept as it is, without prepare()
        $game = new Game;
        $game->data = ['image' => ['fr' => 'medias://'.$media->id]];
        $game->save();
        $this->assertSame('medias://'.$media->id, $this->rawData($game)['image']['fr']);

        $this->otherKey = false;
        $this->useKey('media');
        JsonData::enforceMap();

        $game = new Game;
        $game->data = ['image' => ['fr' => 'medias://'.$media->id]];

        $this->expectException(InvalidJsonReference::class);
        $this->expectExceptionMessage('legacy_id');

        $game->save();
    }

    private function mediasDownloading(Response $response): Medias
    {
        $client = new HttpClient(['handler' => HandlerStack::create(new MockHandler([$response]))]);

        return new class($client) extends Medias
        {
            public function __construct(protected HttpClient $testClient) {}

            protected function newHttpClient(): HttpClient
            {
                return $this->testClient;
            }
        };
    }
}
