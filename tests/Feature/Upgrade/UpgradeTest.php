<?php

namespace Folklore\Tests\Feature\Upgrade;

use Folklore\Models\Media;
use Folklore\ServiceProvider;
use Folklore\Tests\Feature\Upgrade\Fixtures\LegacyArticle;
use Folklore\Tests\Feature\Upgrade\Fixtures\MediaArticle;
use Folklore\Tests\TestCase;
use Folklore\Upgrade\ConfigChange;
use Folklore\Upgrade\ConfigFile;
use Folklore\Upgrade\Finding;
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
        config()->set('site.medias.resource', 'canonical');
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
        $this->assertNotContains('modules.sites', $keys);
        $this->assertNotContains('modules.google', $keys);
    }

    public function test_it_leaves_on_the_modules_that_are_on()
    {
        config()->set('site.modules.medias', true);

        $this->assertNotContains('modules.medias', $this->keys((new Upgrade)->plan($this->siteOf())));
    }

    public function test_it_sets_the_legacy_shape_of_media_resource_for_a_site_that_uses_it()
    {
        $change = $this->change((new Upgrade)->plan($this->siteOf()), 'medias.resource');

        $this->assertSame('legacy', $change->value);
        $this->assertSame(['app/Http/Resources/ArticleMediaResource.php:5'], $change->locations);

        config()->set('site.medias.resource', 'legacy');
        $this->assertNotContains('medias.resource', $this->keys((new Upgrade)->plan($this->siteOf())));
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

    public function test_it_removes_the_rows_of_medias_pivot_without_a_handle_of_models_on_json_data_cast()
    {
        [$article, $media] = $this->seedArticles();
        $legacy = LegacyArticle::query()->firstOrFail();
        $table = $article->medias()->getTable();
        DB::table($table)->insert([
            ['media_id' => $media[0]->getKey(), 'morphable_type' => $article->getMorphClass(), 'morphable_id' => $article->getKey(), 'handle' => null, 'order' => 0],
            ['media_id' => $media[1]->getKey(), 'morphable_type' => $article->getMorphClass(), 'morphable_id' => $article->getKey(), 'handle' => null, 'order' => 1],
            ['media_id' => $media[0]->getKey(), 'morphable_type' => $article->getMorphClass(), 'morphable_id' => $article->getKey(), 'handle' => 'cover', 'order' => 0],
            ['media_id' => $media[0]->getKey(), 'morphable_type' => $legacy->getMorphClass(), 'morphable_id' => $legacy->getKey(), 'handle' => null, 'order' => 0],
        ]);
        $site = $this->siteOf([MediaArticle::class, LegacyArticle::class]);
        $upgrade = new Upgrade;

        $plan = $upgrade->plan($site);

        $this->assertCount(1, $plan->mediaPivotRows);
        $this->assertSame(MediaArticle::class, $plan->mediaPivotRows[0]->model);
        $this->assertSame(2, $plan->mediaPivotRows[0]->count);
        $this->assertSame(2, $upgrade->removeMediaPivotRows($site, $plan->mediaPivotRows[0]));
        $this->assertSame(['cover'], DB::table($table)->where('morphable_type', $article->getMorphClass())->pluck('handle')->all());
        $this->assertSame(1, DB::table($table)->where('morphable_type', $legacy->getMorphClass())->count());
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
            ->expectsOutputToContain("Would set medias.resource → 'legacy'")
            ->expectsOutputToContain('Dry run: nothing was written.')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($this->site.'/config/site.php');
    }

    public function test_the_command_upgrades_the_site_then_has_nothing_to_change()
    {
        [$article, $media] = $this->seedArticles();
        DB::table($article->medias()->getTable())->insert([
            'media_id' => $media[0]->getKey(), 'morphable_type' => $article->getMorphClass(), 'morphable_id' => $article->getKey(), 'handle' => null, 'order' => 0,
        ]);
        $this->app->setBasePath($this->site);

        $this->artisan('folklore:upgrade', ['--model' => [MediaArticle::class, LegacyArticle::class]])
            ->expectsOutputToContain('Removes 1 rows without a handle of `'.MediaArticle::class.'`')
            ->expectsOutputToContain('Removed 1 rows of `medias_pivot` without a handle.')
            ->assertExitCode(0);

        $config = require $this->site.'/config/site.php';
        $this->assertTrue($config['modules']['medias']);
        $this->assertSame('medias', $config['medias']['reference_key']);
        $this->assertSame('legacy', $config['medias']['resource']);
        $this->assertSame(0, DB::table($article->medias()->getTable())->whereNull('handle')->count());

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
        foreach (['One', 'Two'] as $name) {
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
        DB::table('upgrade_media_articles')->insert(['data' => null]);

        return [MediaArticle::query()->firstOrFail(), $media];
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
