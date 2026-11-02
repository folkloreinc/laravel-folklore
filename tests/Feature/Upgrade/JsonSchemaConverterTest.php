<?php

namespace Folklore\Tests\Feature\Upgrade;

use Folklore\Eloquent\MediaUsagesSync;
use Folklore\JsonData\JsonData;
use Folklore\Models\Media;
use Folklore\Tests\Feature\Eloquent\Fixtures\Prize;
use Folklore\Tests\Feature\Eloquent\JsonDataTestCase;
use Folklore\Tests\Feature\Upgrade\Fixtures\Schemas\BuiltContest;
use Folklore\Tests\Feature\Upgrade\Fixtures\Schemas\LegacyContest;
use Folklore\Tests\Feature\Upgrade\Fixtures\Schemas\LegacyGame;
use Folklore\Tests\Feature\Upgrade\Fixtures\Schemas\LegacyGames;
use Folklore\Upgrade\Checks\JsonDeclarationsCheck;
use Folklore\Upgrade\Finding;
use Folklore\Upgrade\JsonSchemaConversion;
use Folklore\Upgrade\JsonSchemaConverter;
use Folklore\Upgrade\ModelFile;
use Folklore\Upgrade\Severity;
use Folklore\Upgrade\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

/**
 * `folklore:upgrade --schemas` on 1.1 models: the schema it writes, and a converted model
 * that reads, writes and syncs what the 1.x model does, from the same stored rows.
 */
class JsonSchemaConverterTest extends JsonDataTestCase
{
    /**
     * @var list<Media>
     */
    private array $media = [];

    /**
     * @var list<Prize>
     */
    private array $prizes = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['site.locales' => ['fr', 'en']]);
        for ($i = 0; $i < 3; $i++) {
            $this->media[] = $this->makeMedia();
            $this->prizes[] = Prize::create(['handle' => 'prize-'.$i]);
        }
    }

    public function test_it_converts_literal_declarations_to_a_schema()
    {
        JsonData::map(['prizes' => Prize::class]);
        $this->seedGames();

        $conversion = $this->conversion(LegacyGame::class);

        $this->assertTrue($conversion->converts());
        $this->assertSame(['getJsonDataRelations', 'getJsonDataColumnExtract'], $conversion->methods);
        $this->assertSame('tests/Feature/Upgrade/Fixtures/Schemas/LegacyGame.php:33', $conversion->location());
        $this->assertStringContainsString(<<<'PHP'
            class LegacyGame extends Model implements ToEntity, HasJsonDataSchema
            {
                use HasMedias;
            PHP, $conversion->code);
        $this->assertStringContainsString(<<<'PHP'
                public function jsonDataSchema(string $attribute): ?ObjectType
                {
                    return match ($attribute) {
                        'data' => Json::object([
                            'image' => Json::media()->translatable(),
                            'gallery' => Json::list(Json::media()),
                            'sponsors' => Json::map(Json::object([
                                'logo' => Json::media(),
                            ])),
                            'cover' => Json::media(),
                            'prizes' => Json::list(Json::object([
                                'prize' => Json::reference('prizes')->relation('prizes'),
                            ])),
                            'source' => Json::reference('prizes'),
                            // TODO(folklore-3): no stored row says what `extras` holds: a map takes any keys; Json::list() for a list, ->translatable() on its node for one value per locale.
                            'extras' => Json::map(Json::media()),
                            'title' => Json::string()->translatable()->extract('title_{locale}'),
                        ])->additional(true),
                        default => null,
                    };
                }

                public function prizes()
            PHP, $conversion->code);
        $this->assertStringNotContainsString('getJsonData', $conversion->code);
        $this->assertStringNotContainsString('use Folklore\\Contracts\\Eloquent\\', $conversion->code);
        $this->assertStringContainsString(<<<'PHP'
            use Folklore\Eloquent\JsonDataCast;
            use Folklore\JsonData\Contracts\HasJsonDataSchema;
            use Folklore\JsonData\Schema\Json;
            use Folklore\JsonData\Schema\ObjectType;
            use Folklore\Mediatheque\Concerns\HasMedias;
            PHP, $conversion->code);
        $this->assertCount(1, $conversion->todos);
    }

    public function test_the_converted_model_reads_the_rows_the_1x_model_wrote()
    {
        JsonData::map(['prizes' => Prize::class]);
        $ids = $this->seedGames();
        $converted = $this->converted();

        foreach ($ids as $id) {
            $legacy = LegacyGame::query()->findOrFail($id);
            $model = $converted::query()->findOrFail($id);

            $this->assertSame($this->normalize($legacy->data), $this->normalize($model->data));
            // The listing gives no relation for `source`, declared with `sync => false`, which
            // neither syncs
            $this->assertEquals(
                JsonData::references($legacy)->map(fn ($entry) => [$entry->attribute, $entry->path, $entry->key, $entry->identifier, $entry->path === 'source' ? null : $entry->relation])->all(),
                JsonData::references($model)->map(fn ($entry) => [$entry->attribute, $entry->path, $entry->key, $entry->identifier, $entry->relation])->all(),
            );
        }
        // The references are resolved, not left as strings
        $this->assertInstanceOf(Media::class, $converted::query()->findOrFail($ids[0])->data['image']['fr']);
    }

    public function test_the_converted_model_writes_and_syncs_what_the_1x_model_does()
    {
        JsonData::map(['prizes' => Prize::class]);
        $this->seedGames();
        $converted = $this->converted();

        $results = [];
        foreach ([LegacyGame::class, $converted] as $class) {
            /** @var Model $model */
            $model = new $class(['handle' => 'quiz']);
            $model->data = $this->input();
            $model->save();
            $model = $model->fresh() ?? $model;

            $results[] = [
                'data' => $this->rawData($model),
                'columns' => [$model->getAttribute('title_fr'), $model->getAttribute('title_en')],
                'medias' => $model->medias(MediaUsagesSync::HANDLE)->pluck('medias.id')->sort()->values()->all(),
                'prizes' => $model->prizes()->pluck('prizes.id')->sort()->values()->all(),
            ];
        }

        $this->assertSame($results[0], $results[1]);
        $this->assertSame(['Jeu', 'Game'], $results[0]['columns']);
        $this->assertSame('prizes://'.$this->prizes[2]->id, $results[0]['data']['source']);
        // `source` has `sync => false`
        $this->assertSame([$this->prizes[0]->id], $results[0]['prizes']);
    }

    public function test_the_repository_stores_what_it_stored_for_the_1x_model()
    {
        JsonData::map(['prizes' => Prize::class]);
        $this->seedGames();
        $converted = $this->converted();

        $stored = [];
        foreach ([LegacyGame::class, $converted] as $class) {
            $repository = new LegacyGames($class);
            $entity = $repository->create(['handle' => 'quiz', ...$this->input(), 'notes' => ['free' => 'text']]);
            $model = $class::query()->findOrFail($entity->id());
            $stored[] = $this->rawData($model);

            $repository->update($entity->id(), ['gallery' => [$this->media[2]], 'notes' => 'replaced']);
            $stored[] = $this->rawData($class::query()->findOrFail($entity->id()));
        }

        $this->assertSame($stored[0], $stored[2]);
        $this->assertSame($stored[1], $stored[3]);
        // A key that isn't in the schema, stored by `$jsonAttributeFillable = '*'`
        $this->assertSame('replaced', $stored[3]['notes']);
    }

    public function test_it_leaves_declarations_that_a_schema_cant_give_with_a_todo()
    {
        $conversion = $this->conversion(LegacyContest::class);

        $this->assertFalse($conversion->converts());
        $this->assertSame([
            '`prizes://` isn\'t mapped, and a schema writes mapped keys only',
            '`links.*` has `delete`, which a schema node doesn\'t have',
        ], $conversion->blockers);
        $this->assertSame(['prizes' => Prize::class], $conversion->keysToMap);
        $this->assertStringContainsString(
            "    // TODO(folklore-3): not converted to jsonDataSchema() by folklore:upgrade --schemas: `prizes://` isn't mapped, and a schema writes mapped keys only; `links.*` has `delete`, which a schema node doesn't have.\n    public function getJsonDataRelations(",
            $conversion->code,
        );
        $this->assertStringContainsString('implements HasJsonDataRelations', $conversion->code);
    }

    public function test_it_leaves_a_built_return()
    {
        $conversion = $this->conversion(BuiltContest::class);

        $this->assertSame(['`getJsonDataRelations()` builds its array: write the schema by hand'], $conversion->blockers);
        $this->assertStringContainsString('    // TODO(folklore-3): not converted', $conversion->code);
    }

    public function test_it_replaces_its_todo_when_it_runs_again()
    {
        $conversion = $this->conversion(LegacyContest::class);
        $again = (new ModelFile($conversion->code));
        $again->commentMethod('getJsonDataRelations', 'another reason.');

        $this->assertSame(1, substr_count($again->code(), 'TODO(folklore-3)'));
        $this->assertStringContainsString('// TODO(folklore-3): another reason.', $again->code());
    }

    public function test_a_star_decided_by_the_stored_data()
    {
        JsonData::map(['prizes' => Prize::class]);
        // Lists in one row, objects in another
        DB::table('games')->insert([
            ['data' => json_encode(['gallery' => ['medias://1']])],
            ['data' => json_encode(['gallery' => ['a' => 'medias://1']])],
        ]);

        $conversion = $this->conversion(LegacyGame::class);

        $this->assertStringContainsString("'gallery' => Json::map(Json::media()),", $conversion->code);
        $this->assertContains('`gallery` is stored as a list in 1 rows and as an object in 1: a map takes both, Json::list() refuses the objects.', $conversion->todos);
    }

    public function test_the_check_reports_what_converts_and_what_doesnt()
    {
        JsonData::map(['prizes' => Prize::class]);
        $this->seedGames();
        $site = $this->site([LegacyGame::class, BuiltContest::class]);

        $findings = [...(new JsonDeclarationsCheck)->check($site)];

        $this->assertSame('1 of the 2 models with 1.x JSON declarations convert to `jsonDataSchema()`, which reads and writes the same data. The others keep their declarations until 3.0.', $findings[0]->message);
        $game = $this->finding($findings, LegacyGame::class);
        $this->assertSame(Severity::Deprecated, $game->severity);
        $this->assertStringContainsString('converts them to `jsonDataSchema()`, with 1 `TODO(folklore-3)` to check.', $game->message);
        $built = $this->finding($findings, BuiltContest::class);
        $this->assertStringContainsString('builds its array', $built->message);
        // The check writes nothing
        $this->assertStringContainsString('getJsonDataRelations', (string) file_get_contents(__DIR__.'/Fixtures/Schemas/LegacyGame.php'));
    }

    public function test_the_check_says_which_keys_to_map()
    {
        $finding = $this->finding([...(new JsonDeclarationsCheck)->check($this->site([LegacyContest::class]))], LegacyContest::class);

        $this->assertStringContainsString("`JsonData::map(['prizes' => \\".Prize::class.'::class])`', (string) $finding->fix);
    }

    public function test_the_check_lists_the_repositories_that_keep_the_1x_fill()
    {
        JsonData::map(['prizes' => Prize::class]);
        $site = new Site((string) realpath(__DIR__.'/../../..'), $this->app['config'], $this->app['db'], ['tests/Feature/Upgrade/Fixtures/Schemas'], [LegacyGame::class]);

        $finding = $this->finding([...(new JsonDeclarationsCheck)->check($site)], '$jsonAttributeFillable');

        $this->assertSame(['tests/Feature/Upgrade/Fixtures/Schemas/LegacyGames.php:14'], $finding->locations);
    }

    public function test_the_command_says_what_it_would_convert_and_writes_nothing_on_a_dry_run()
    {
        JsonData::map(['prizes' => Prize::class]);
        $this->app->setBasePath((string) realpath(__DIR__.'/../../..'));
        $before = file_get_contents(__DIR__.'/Fixtures/Schemas/LegacyGame.php');

        $this->artisan('folklore:upgrade', ['--schemas' => true, '--dry-run' => true, '--path' => ['tests/Feature/Upgrade/Fixtures/Schemas']])
            ->expectsOutputToContain('Would convert '.LegacyGame::class.' to `jsonDataSchema()` (tests/Feature/Upgrade/Fixtures/Schemas/LegacyGame.php:33)')
            ->expectsOutputToContain('Would leave '.LegacyContest::class)
            ->expectsOutputToContain('Dry run: nothing was written.')
            ->assertSuccessful();

        $this->assertSame($before, file_get_contents(__DIR__.'/Fixtures/Schemas/LegacyGame.php'));
    }

    public function test_it_writes_the_file_and_has_nothing_left_to_do_after()
    {
        $base = sys_get_temp_dir().'/folklore-schemas-'.bin2hex(random_bytes(4));
        $class = 'TempContest'.bin2hex(random_bytes(4));
        $path = $base.'/app/Models/'.$class.'.php';
        mkdir(dirname($path), 0755, true);
        // The temporary directory is a symbolic link on macOS
        $base = (string) realpath($base);
        $path = $base.'/app/Models/'.$class.'.php';
        file_put_contents($path, str_replace(
            ['namespace Folklore\\Tests\\Feature\\Upgrade\\Fixtures\\Schemas;', 'class BuiltContest ', 'array_merge([\'logo\' => \'medias\'], $this->blockRelations())'],
            ['namespace Folklore\\Tests\\Temp;', 'class '.$class.' ', "['logo' => 'medias']"],
            (string) file_get_contents(__DIR__.'/Fixtures/Schemas/BuiltContest.php'),
        ));
        require_once $path;
        $site = new Site($base, $this->app['config'], $this->app['db'], [], ['Folklore\\Tests\\Temp\\'.$class]);
        $converter = new JsonSchemaConverter;

        try {
            $conversion = $converter->convert($site, 'Folklore\\Tests\\Temp\\'.$class);
            $this->assertNotNull($conversion);
            $this->assertTrue($conversion->changes());
            $converter->write($conversion);

            $converted = (string) file_get_contents($path);
            $this->assertStringContainsString("'logo' => Json::media(),", $converted);
            $this->assertStringNotContainsString('getJsonDataRelations', $converted);
            // Its file declares no 1.x method any more
            $this->assertNull($converter->convert($site, 'Folklore\\Tests\\Temp\\'.$class));
        } finally {
            (new Filesystem)->deleteDirectory($base);
        }
    }

    /**
     * The values of a converted model, as stored with the 1.x model.
     *
     * @return array<string, mixed>
     */
    private function input(): array
    {
        return [
            'image' => ['fr' => $this->media[0], 'en' => 'medias://'.$this->media[1]->id],
            'gallery' => [$this->media[0], $this->media[1]],
            'sponsors' => ['acme' => ['logo' => $this->media[2], 'name' => 'Acme']],
            'cover' => $this->media[2],
            'prizes' => [['prize' => $this->prizes[0], 'rank' => 1]],
            'source' => $this->prizes[2],
            'title' => ['fr' => 'Jeu', 'en' => 'Game'],
            'other' => ['kept' => true],
        ];
    }

    /**
     * Rows written by the 1.x model, and rows written by 1.x code without events.
     *
     * @return list<int>
     */
    private function seedGames(): array
    {
        $game = new LegacyGame(['handle' => 'quiz']);
        $game->data = $this->input();
        $game->save();

        $id = DB::table('games')->insertGetId(['data' => json_encode([
            'image' => ['fr' => 'medias://'.$this->media[1]->id],
            'gallery' => [],
            'sponsors' => ['other' => ['logo' => 'medias://'.$this->media[0]->id]],
            'prizes' => [['prize' => 'prizes://'.$this->prizes[1]->id]],
            'source' => 'prizes://999',
        ])]);

        return [(int) $game->getKey(), $id];
    }

    /**
     * The class of the converted `LegacyGame`, under another name.
     *
     * @return class-string<Model>
     */
    private function converted(): string
    {
        $code = $this->conversion(LegacyGame::class)->code;
        $name = 'ConvertedGame'.bin2hex(random_bytes(4));
        eval(str_replace(['<?php', 'class LegacyGame '], ['', 'class '.$name.' '], $code));

        /** @var class-string<Model> */
        return 'Folklore\\Tests\\Feature\\Upgrade\\Fixtures\\Schemas\\'.$name;
    }

    private function conversion(string $class): JsonSchemaConversion
    {
        $conversion = (new JsonSchemaConverter)->convert($this->site([$class]), $class);
        $this->assertNotNull($conversion);

        return $conversion;
    }

    /**
     * @param  list<class-string<Model>>  $models
     */
    private function site(array $models): Site
    {
        return new Site((string) realpath(__DIR__.'/../../..'), $this->app['config'], $this->app['db'], [], $models);
    }

    /**
     * Models as their class and key, to compare the values of two models.
     */
    private function normalize(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return [$value::class, $value->getKey()];
        }

        return is_array($value) ? array_map(fn ($item) => $this->normalize($item), $value) : $value;
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function finding(array $findings, string $model): Finding
    {
        foreach ($findings as $finding) {
            if (str_contains($finding->message, '`'.$model.'`')) {
                return $finding;
            }
        }
        $this->fail("No finding about {$model}.");
    }
}
