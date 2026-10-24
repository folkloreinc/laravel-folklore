<?php

namespace Folklore\Tests\Feature\Eloquent;

use Folklore\Eloquent\MediaUsagesSync;
use Folklore\Eloquent\SiteLocaleResolver;
use Folklore\JsonData\Contracts\LocaleResolver;
use Folklore\JsonData\Exceptions\InvalidJsonData;
use Folklore\JsonData\JsonData;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ReferenceType;
use Folklore\Models\Media;
use Folklore\Tests\Feature\Eloquent\Fixtures\SchemaGame;
use Folklore\Tests\Feature\Eloquent\Fixtures\SchemaGames;
use Illuminate\Support\Facades\DB;

/**
 * What this package adds to the schema of laravel-json-data: `Json::media()`, the site's
 * locales, and the base repository filling with `JsonData::fill()` (docs/json-data.md,
 * "Schemas").
 */
class JsonDataSchemaTest extends JsonDataTestCase
{
    private string $referenceKey = 'media';

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('site.medias.reference_key', $this->referenceKey);
    }

    private function useKey(string $key): void
    {
        $this->referenceKey = $key;
        $this->refreshApplication();
        $this->setUp();
    }

    private function mediaReference(Media $media): string
    {
        return $this->referenceKey === 'media' ? 'media://'.$media->uid : 'medias://'.$media->id;
    }

    public function test_json_media_writes_the_key_of_the_medias_module_and_syncs_the_media()
    {
        foreach (['media', 'medias'] as $key) {
            $this->useKey($key);
            $media = $this->makeMedia();

            $node = Json::media();
            $this->assertInstanceOf(ReferenceType::class, $node);
            $this->assertSame($key, $node->getKey());
            $this->assertSame('medias', $node->getRelation());

            $game = new SchemaGame(['handle' => 'quiz']);
            JsonData::fill($game, ['cover' => $media]);
            $game->save();

            $this->assertSame($this->mediaReference($media), $this->rawData($game)['cover']);
            $this->assertSame([$media->id], $game->fresh()?->medias(MediaUsagesSync::HANDLE)->map->getKey()->all());
            $this->assertTrue($game->fresh()?->data['cover']->is($media));
        }
    }

    public function test_the_locales_come_from_laravel_locale_then_from_site_locales()
    {
        $resolver = $this->app->make(LocaleResolver::class);
        $this->assertInstanceOf(SiteLocaleResolver::class, $resolver);
        $this->assertSame(['fr', 'en'], $resolver->locales());

        config(['site.locales' => ['en', 'es'], 'app.locale' => 'es']);
        $this->assertSame(['en', 'es'], $resolver->locales());
        $this->assertSame('es', $resolver->defaultLocale());

        config(['site.locales' => null, 'locale.locales' => null, 'json-data.locales' => ['de']]);
        $this->assertSame(['de'], $resolver->locales());
    }

    public function test_a_string_given_to_a_translatable_node_goes_into_the_site_default_locale()
    {
        config(['app.locale' => 'fr']);

        $game = new SchemaGame;
        JsonData::fill($game, ['title' => 'Le quiz', 'image' => $this->makeMedia()]);

        $this->assertSame(['fr' => 'Le quiz'], $game->data['title']);
        $this->assertSame(['fr'], array_keys($game->data['image']));
    }

    public function test_the_repository_fills_a_model_with_a_schema_from_a_flat_input()
    {
        config(['app.locale' => 'fr']);
        $image = $this->makeMedia();
        $games = $this->app->make(SchemaGames::class);

        $entity = $games->create([
            'handle' => 'quiz',
            'title' => ['fr' => 'Le quiz', 'en' => 'The quiz'],
            'subtitle' => 'Trois questions',
            'image' => $image,
        ]);
        $game = $entity->getModel();

        $this->assertSame('quiz', $game->handle);
        $this->assertSame('Le quiz', $game->title_fr);
        $this->assertSame('The quiz', $game->title_en);
        $this->assertEquals([
            'title' => ['fr' => 'Le quiz', 'en' => 'The quiz'],
            'intro' => 'Trois questions',
            'image' => ['fr' => $this->mediaReference($image)],
        ], $this->rawData($game));
        $this->assertSame([$image->id], $game->medias(MediaUsagesSync::HANDLE)->map->getKey()->all());

        $games->update($entity->id(), ['title' => 'Le nouveau quiz', 'subtitle' => null]);

        $this->assertEquals([
            'title' => ['fr' => 'Le nouveau quiz', 'en' => 'The quiz'],
            'intro' => null,
            'image' => ['fr' => $this->mediaReference($image)],
        ], $this->rawData($game));
        $this->assertSame('Le nouveau quiz', DB::table('games')->where('id', $game->id)->value('title_fr'));
    }

    public function test_the_repository_refuses_the_keys_that_are_neither_fillable_nor_in_the_schema()
    {
        $games = $this->app->make(SchemaGames::class);

        try {
            $games->create(['handle' => 'quiz', 'title' => 'Le quiz', 'colour' => 'red']);
            $this->fail('The unknown key was not refused.');
        } catch (InvalidJsonData $exception) {
            $this->assertStringContainsString('[colour]', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('games')->count());
    }
}
