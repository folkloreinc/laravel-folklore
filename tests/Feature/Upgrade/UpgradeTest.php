<?php

namespace Folklore\Tests\Feature\Upgrade;

use Folklore\Models\Media;
use Folklore\ServiceProvider;
use Folklore\Tests\Feature\Upgrade\Fixtures\LegacyArticle;
use Folklore\Tests\Feature\Upgrade\Fixtures\MediaArticle;
use Folklore\Tests\Feature\Upgrade\Fixtures\PlainMediaArticle;
use Folklore\Tests\TestCase;
use Folklore\Upgrade\ConfigChange;
use Folklore\Upgrade\ConfigFile;
use Folklore\Upgrade\Finding;
use Folklore\Upgrade\MediaPivotRows;
use Folklore\Upgrade\Site;
use Folklore\Upgrade\Upgrade;
use Folklore\Upgrade\UpgradePlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `folklore:upgrade` on a copy of tests/fixture/upgrade-site: what it sets in
 * `config/site.php`, and the rows of `medias_pivot` it removes.
 */
class UpgradeTest extends TestCase
{
    private string $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = sys_get_temp_dir().'/folklore-upgrade-site-'.bin2hex(random_bytes(4));
        (new Filesystem)->copyDirectory(UpgradeCheckTest::SITE, $this->site);

        foreach (array_keys(ServiceProvider::MODULES) as $module) {
            config()->set('site.modules.'.$module, false);
        }
        config()->set('site.medias.reference_key', 'media');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->site);

        parent::tearDown();
    }

    public function test_it_turns_on_the_modules_the_site_uses()
    {
        $keys = $this->keys((new Upgrade)->plan($this->siteOf()));

        $this->assertContains('modules.medias', $keys);
        $this->assertContains('modules.pages', $keys);
        $this->assertContains('modules.auth', $keys);
        $this->assertContains('modules.http', $keys);
        $this->assertContains('modules.customerio', $keys);
        // `php artisan assets:view` in Envoy.blade.php
        $this->assertContains('modules.assets', $keys);
        $this->assertNotContains('modules.sites', $keys);
        $this->assertNotContains('modules.google', $keys);
    }

    public function test_it_leaves_on_the_modules_that_are_on()
    {
        config()->set('site.modules.medias', true);

        $this->assertNotContains('modules.medias', $this->keys((new Upgrade)->plan($this->siteOf())));
    }

    public function test_it_sets_the_medias_reference_key_when_medias_references_are_stored()
    {
        $site = $this->siteOf([LegacyArticle::class]);
        $this->assertNotContains('medias.reference_key', $this->keys((new Upgrade)->plan($site)));

        $this->seedArticles();

        $change = $this->change((new Upgrade)->plan($this->siteOf([LegacyArticle::class])), 'medias.reference_key');
        $this->assertSame('medias', $change->value);
        $this->assertSame([LegacyArticle::class.'::data'], $change->locations);
    }

    public function test_it_writes_the_values_in_the_site_config_file()
    {
        $site = $this->siteOf();
        $upgrade = new Upgrade;

        $path = $upgrade->writeConfig($site, (new Upgrade)->plan($site)->config);

        $this->assertSame($this->site.'/config/site.php', $path);
        $file = new ConfigFile((string) file_get_contents($path));
        $this->assertTrue($file->get('modules.medias'));
        $this->assertFalse($file->get('modules.google'));
        $this->assertSame('legacy', $file->get('medias.resource'));
        $this->assertTrue(config('site.modules.medias'));
        $this->assertSame((require $path)['modules']['medias'], true);
    }

    public function test_it_edits_the_config_file_that_the_site_has()
    {
        file_put_contents($this->site.'/config/site.php', "<?php\n\nreturn [\n    'title' => env('SITE_TITLE'),\n];\n");

        (new Upgrade)->writeConfig($this->siteOf(), [new ConfigChange('modules.medias', true, 'Used.')]);

        $this->assertSame(
            "<?php\n\nreturn [\n    'title' => env('SITE_TITLE'),\n    'modules' => [\n        'medias' => true,\n    ],\n];\n",
            file_get_contents($this->site.'/config/site.php'),
        );
    }

    public function test_it_leaves_the_values_that_are_not_literals_to_the_site()
    {
        file_put_contents($this->site.'/config/site.php', "<?php\n\nreturn [\n    'modules' => [\n        'medias' => env('FOLKLORE_MEDIAS', false),\n    ],\n];\n");

        $plan = (new Upgrade)->plan($this->siteOf());

        $this->assertNotContains('modules.medias', $this->keys($plan));
        $this->assertContains('modules.http', $this->keys($plan));
        $note = $this->note($plan, '`site.modules.medias` can\'t be written in config/site.php: `modules.medias` is not a literal value');
        $this->assertSame('Set `site.modules.medias` to `true` yourself. The site uses the `medias` module.', $note->fix);

        (new Upgrade)->writeConfig($this->siteOf(), $plan->config);
        $this->assertStringContainsString("'medias' => env('FOLKLORE_MEDIAS', false),", (string) file_get_contents($this->site.'/config/site.php'));
    }

    public function test_it_removes_the_rows_without_a_handle_of_the_media_that_the_json_data_syncs()
    {
        [$article, $media] = $this->seedArticles();
        $plain = PlainMediaArticle::query()->firstOrFail();
        $table = $article->medias()->getTable();
        $this->attach($article, [[$media[0], null], [$media[1], null], [$media[2], null], [$media[0], 'cover']]);
        $this->attach($plain, [[$media[0], null]]);
        $site = $this->siteOf([MediaArticle::class, PlainMediaArticle::class, LegacyArticle::class]);
        $upgrade = new Upgrade;

        $plan = $upgrade->plan($site);

        $rows = $this->pivotRows($plan, MediaArticle::class);
        $this->assertSame(2, $rows->count);
        $this->assertSame(1, $rows->models);
        $this->assertSame(0, $this->pivotRows($plan, PlainMediaArticle::class)->count);
        $this->assertSame(2, $upgrade->removeMediaPivotRows($site, $rows));
        $this->assertEqualsCanonicalizing([
            [$media[0]->getKey(), 'cover'],
            [$media[0]->getKey(), 'json'],
            [$media[1]->getKey(), 'json'],
            [$media[2]->getKey(), null],
        ], $this->pivot($table, $article));
        $this->assertSame([[$media[0]->getKey(), null]], $this->pivot($table, $plain));
    }

    public function test_a_model_whose_json_references_no_media_keeps_its_rows()
    {
        [$article, $media] = $this->seedArticles();
        DB::table('upgrade_media_articles')->update(['data' => null]);
        $this->attach($article, [[$media[0], null]]);

        $plan = (new Upgrade)->plan($this->siteOf([MediaArticle::class]));

        $this->assertSame(0, $this->pivotRows($plan, MediaArticle::class)->count);
    }

    public function test_it_notes_the_models_with_has_medias_that_do_not_load()
    {
        $note = $this->note((new Upgrade)->plan($this->siteOf()), 'App\\Models\\Page` uses `HasMedias` and doesn\'t load');

        $this->assertSame(['app/Models/Page.php'], $note->locations);
    }

    public function test_it_leaves_the_blocks_to_convert()
    {
        Schema::create('blocks_pivot', function (Blueprint $table) {
            $table->unsignedBigInteger('block_id');
            $table->morphs('blockable');
        });
        DB::table('blocks_pivot')->insert(['block_id' => 1, 'blockable_type' => 'page', 'blockable_id' => 1]);

        $this->assertNotNull($this->note((new Upgrade)->plan($this->siteOf()), '`blocks_pivot` links 1 blocks'));
    }

    public function test_the_dry_run_writes_nothing()
    {
        $this->app->setBasePath($this->site);

        $this->artisan('folklore:upgrade', ['--dry-run' => true])
            ->expectsOutputToContain('Would set modules.medias → true')
            ->expectsOutputToContain('Would set modules.http → true')
            ->expectsOutputToContain('Dry run: nothing was written.')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($this->site.'/config/site.php');
    }

    public function test_the_command_upgrades_the_site_then_has_nothing_to_change()
    {
        [$article, $media] = $this->seedArticles();
        $this->attach($article, [[$media[0], null]]);
        $this->app->setBasePath($this->site);

        $this->artisan('folklore:upgrade', ['--model' => [MediaArticle::class, LegacyArticle::class]])
            ->expectsOutputToContain('Removes 1 rows without a handle of 1 rows of `'.MediaArticle::class.'`')
            ->expectsOutputToContain('Removed 1 rows of `medias_pivot` without a handle, whose media are synced under the `json` handle.')
            ->assertExitCode(0);

        $config = require $this->site.'/config/site.php';
        $this->assertTrue($config['modules']['medias']);
        $this->assertSame('medias', $config['medias']['reference_key']);
        $this->assertSame('legacy', $config['medias']['resource'], 'The package\'s default stays.');
        $this->assertEqualsCanonicalizing([[$media[0]->getKey(), 'json'], [$media[1]->getKey(), 'json']], $this->pivot($article->medias()->getTable(), $article));

        $this->artisan('folklore:upgrade', ['--model' => [MediaArticle::class, LegacyArticle::class]])
            ->expectsOutputToContain('Nothing to change.')
            ->assertExitCode(0);
    }

    /**
     * @param  list<class-string<Model>>  $models
     */
    private function siteOf(array $models = []): Site
    {
        return new Site($this->site, $this->app['config'], $this->app['db'], Site::PATHS, $models);
    }

    /**
     * @return array{MediaArticle, list<Media>}
     */
    private function seedArticles(): array
    {
        $this->migrateMediatheque();
        $media = [];
        foreach (['One', 'Two', 'Three'] as $name) {
            $model = new Media;
            $model->forceFill(['type' => 'image', 'name' => $name, 'status' => 'ready', 'visibility' => 'public'])->save();
            $media[] = $model;
        }

        Schema::create('upgrade_legacy_articles', function (Blueprint $table) {
            $table->id();
            $table->json('data')->nullable();
        });
        Schema::create('upgrade_media_articles', function (Blueprint $table) {
            $table->id();
            $table->json('data')->nullable();
        });
        DB::table('upgrade_legacy_articles')->insert([
            'data' => json_encode(['gallery' => ['medias://'.$media[0]->getKey(), 'medias://'.$media[1]->getKey()]]),
        ]);
        DB::table('upgrade_media_articles')->insert([
            'data' => json_encode(['gallery' => ['medias://'.$media[0]->getKey(), 'medias://'.$media[1]->getKey()]]),
        ]);

        return [MediaArticle::query()->firstOrFail(), $media];
    }

    /**
     * @param  list<array{Media, string|null}>  $media
     */
    private function attach(Model $model, array $media): void
    {
        foreach ($media as $index => [$item, $handle]) {
            DB::table($model->medias()->getTable())->insert([
                'media_id' => $item->getKey(),
                'morphable_type' => $model->getMorphClass(),
                'morphable_id' => $model->getKey(),
                'handle' => $handle,
                'order' => $index,
            ]);
        }
    }

    /**
     * @return list<array{int, string|null}>
     */
    private function pivot(string $table, Model $model): array
    {
        return DB::table($table)
            ->where('morphable_type', $model->getMorphClass())
            ->where('morphable_id', $model->getKey())
            ->get()
            ->map(fn (object $row) => [(int) $row->media_id, $row->handle])
            ->all();
    }

    private function pivotRows(UpgradePlan $plan, string $model): MediaPivotRows
    {
        foreach ($plan->mediaPivotRows as $rows) {
            if ($rows->model === $model) {
                return $rows;
            }
        }
        $this->fail("The plan has no rows of [{$model}].");
    }

    /**
     * @return list<string>
     */
    private function keys(UpgradePlan $plan): array
    {
        return array_map(fn (ConfigChange $change) => $change->key, $plan->config);
    }

    private function change(UpgradePlan $plan, string $key): ConfigChange
    {
        foreach ($plan->config as $change) {
            if ($change->key === $key) {
                return $change;
            }
        }
        $this->fail("The plan doesn't set [{$key}]: ".implode(', ', $this->keys($plan)));
    }

    private function note(UpgradePlan $plan, string $message): Finding
    {
        foreach ($plan->notes as $note) {
            if (str_contains($note->message, $message)) {
                return $note;
            }
        }
        $this->fail("No note says [{$message}]: ".implode(' | ', array_map(fn (Finding $note) => $note->message, $plan->notes)));
    }
}
