<?php

namespace Folklore\Tests\Feature\Upgrade;

use Folklore\Eloquent\JsonData;
use Folklore\Models\Media;
use Folklore\ServiceProvider;
use Folklore\Tests\Feature\Upgrade\Fixtures\BrokenModel;
use Folklore\Tests\Feature\Upgrade\Fixtures\CastArticle;
use Folklore\Tests\Feature\Upgrade\Fixtures\LegacyArticle;
use Folklore\Tests\TestCase;
use Folklore\Upgrade\Checks\BlocksCheck;
use Folklore\Upgrade\Checks\Check;
use Folklore\Upgrade\Checks\DeprecatedApiCheck;
use Folklore\Upgrade\Checks\JsonCodeCheck;
use Folklore\Upgrade\Checks\MediasCheck;
use Folklore\Upgrade\Checks\ModulesCheck;
use Folklore\Upgrade\Checks\PackagesCheck;
use Folklore\Upgrade\Checks\PanneauContractsCheck;
use Folklore\Upgrade\Checks\RemovedModulesCheck;
use Folklore\Upgrade\Checks\StoredReferencesCheck;
use Folklore\Upgrade\Finding;
use Folklore\Upgrade\Severity;
use Folklore\Upgrade\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `folklore:upgrade --check` on tests/fixture/upgrade-site, a 1.1 site that does what the
 * active 1.1 sites do.
 */
class UpgradeCheckTest extends TestCase
{
    public const SITE = __DIR__.'/../../fixture/upgrade-site';

    public function test_it_reports_the_modules_the_site_uses_and_that_are_off()
    {
        $this->turnModulesOff();

        $findings = $this->check(new ModulesCheck);

        $medias = $this->finding($findings, 'the `medias` module, which is off');
        $this->assertSame(Severity::Todo, $medias->severity);
        $this->assertContains('app/Entities/Image.php:5 (Folklore\\Entities\\Image)', $medias->locations);
        $this->assertSame(['config/auth.php:8 (the `repository` auth provider)'], $this->finding($findings, '`auth` module')->locations);
        $this->assertSame(['app/Http/Controllers/ExportController.php:5 (Folklore\\Contracts\\Services\\CustomerIo)'], $this->finding($findings, '`customerio` module')->locations);
        $this->assertContains('app/Models/Page.php:6 (Folklore\\Models\\Page)', $this->finding($findings, '`pages` module')->locations);
    }

    public function test_a_macro_that_the_site_defines_itself_needs_no_module()
    {
        $this->turnModulesOff();

        $http = $this->finding($this->check(new ModulesCheck), '`http` module');

        $this->assertSame([
            'resources/views/app.blade.php:1 (the `routeForReactRouter` URL macro)',
            'app/Http/Controllers/ExportController.php:16 (the `pixel` response macro)',
        ], $http->locations);
    }

    public function test_a_module_that_is_on_is_reported_as_used()
    {
        $medias = $this->finding($this->check(new ModulesCheck), '`medias` module');

        $this->assertSame(Severity::Info, $medias->severity);
    }

    public function test_it_reports_the_removed_modules_and_skips_comments()
    {
        $findings = $this->check(new RemovedModulesCheck);

        $this->assertSame(['app/Providers/AppServiceProvider.php:6 (Folklore\\Site)'], $this->finding($findings, 'site manager')->locations);
        $this->assertSame(Severity::Breaking, $findings[0]->severity);
        $this->assertNull($this->finding($findings, 'Contact', required: false));
    }

    public function test_it_reports_the_packages_the_site_no_longer_gets()
    {
        $this->turnModulesOff();

        $findings = $this->check(new PackagesCheck);
        $messages = array_map(fn (Finding $finding) => $finding->message, $findings);

        $this->assertNotNull($this->finding($findings, '`folklore/laravel-image`'));
        $this->assertNotNull($this->finding($findings, '`laravel/fortify`'));
        $this->assertStringContainsString('the view composers', $this->finding($findings, '`folklore/laravel-locale`')->message);
        $this->assertNull($this->finding($findings, '`folklore/laravel-mediatheque`', required: false), implode("\n", $messages));
    }

    public function test_it_reports_panneau_contracts_used_through_folklore()
    {
        $findings = $this->check(new PanneauContractsCheck);

        $this->assertSame(['app/Repositories/Concerns/WritesReferences.php:5'], $this->finding($findings, 'ResourceItem')->locations);
        $this->assertSame(['app/Providers/AppServiceProvider.php:9'], $this->finding($findings, 'Contracts\\Repository`')->locations);
    }

    public function test_it_reports_plain_json_casts_over_json_data_cast()
    {
        $findings = $this->check(new JsonCodeCheck);

        $cast = $this->finding($findings, '`App\\Models\\Page` casts `data` with a plain `json` cast');
        $this->assertSame(['app/Models/Page.php:13'], $cast->locations);
        $this->assertNull($this->finding($findings, 'App\\Models\\Article', required: false));
    }

    public function test_it_reports_the_code_that_writes_references()
    {
        $writers = $this->finding($this->check(new JsonCodeCheck), 'writes `scheme://id` references itself');

        $this->assertSame(Severity::Todo, $writers->severity);
        $this->assertSame(['app/Repositories/Articles.php:28 ("media://{$)'], $writers->locations);
    }

    public function test_code_with_the_key_in_a_variable_may_build_urls_and_is_only_reported()
    {
        $variables = $this->finding($this->check(new JsonCodeCheck), 'with a key in a variable');

        // Not `$scheme.'://'.$host`
        $this->assertSame(Severity::Info, $variables->severity);
        $this->assertSame(["app/Repositories/Concerns/WritesReferences.php:13 (.'://'.)"], $variables->locations);
    }

    public function test_it_reports_the_pivot_syncs_of_the_classes_that_use_that_code()
    {
        $syncs = $this->finding($this->check(new JsonCodeCheck), 'Pivot syncs');

        // Not the syncs of a class that only imports the trait, nor `attach()`
        $this->assertSame(Severity::Info, $syncs->severity);
        $this->assertSame(['app/Repositories/Articles.php:22'], $syncs->locations);
    }

    public function test_it_reports_the_media_api_that_changes()
    {
        $findings = $this->check(new MediasCheck);

        $this->assertSame(['app/Models/Page.php:5'], $this->finding($findings, 'Support\\Traits\\HasMedias')->locations);
        $this->assertSame(['app/Entities/Image.php:13', 'config/image.php:4'], $this->finding($findings, '`image.sizes`')->locations);
        $this->assertSame(['app/Entities/Image.php:13'], $this->finding($findings, 'new ImageSize')->locations);
        $this->assertSame(['app/Entities/Image.php:23'], $this->finding($findings, 'getMetadatas')->locations);
        $this->assertSame(Severity::Deprecated, $this->finding($findings, 'setFilters')->severity);
    }

    public function test_it_reports_media_resource_in_the_canonical_shape()
    {
        $this->assertNull($this->finding($this->check(new MediasCheck), 'is `canonical`', required: false));

        config()->set('site.medias.resource', 'canonical');
        $canonical = $this->finding($this->check(new MediasCheck), 'is `canonical`');

        $this->assertSame(Severity::Todo, $canonical->severity);
        $this->assertSame(['app/Http/Resources/ImageResource.php:5'], $canonical->locations);
    }

    public function test_it_reports_subclasses_of_media_resource_that_break()
    {
        $findings = $this->check(new MediasCheck);

        $this->assertSame(['app/Http/Resources/ImageResource.php:9'], $this->finding($findings, 'without types')->locations);
        $this->assertSame(['app/Http/Resources/ImageResource.php:12'], $this->finding($findings, 'calls the entity through the resource')->locations);
    }

    public function test_a_model_that_is_a_fatal_error_is_reported_without_stopping_the_check()
    {
        $site = new Site(self::SITE, $this->app['config'], $this->app['db'], [__DIR__.'/Fixtures']);

        $findings = array_values([...(new StoredReferencesCheck)->check($site)]);

        $broken = $this->finding($findings, BrokenModel::class.'` doesn\'t load in 2.0');
        $this->assertSame(Severity::Todo, $broken->severity);
        $this->assertStringContainsString('getKeyName', $broken->message);
        $this->assertFalse(class_exists(BrokenModel::class, false));
    }

    public function test_it_reports_the_deprecated_api()
    {
        $findings = $this->check(new DeprecatedApiCheck);

        $this->assertSame(['app/Repositories/Articles.php:17'], $this->finding($findings, 'JsonDataCast::macro')->locations);
        $this->assertSame(['app/Repositories/Articles.php:13'], $this->finding($findings, '$jsonAttributeName')->locations);
        $this->assertSame(['app/Repositories/Tags.php:16'], $this->finding($findings, 'setGlobalQuery')->locations);
    }

    public function test_it_reports_the_paths_and_keys_of_references_in_plain_json()
    {
        [$media] = $this->seedReferences();

        $findings = $this->check(new StoredReferencesCheck, [LegacyArticle::class]);

        $plain = $this->finding($findings, LegacyArticle::class.'::data` holds references');
        $this->assertSame(Severity::Todo, $plain->severity);
        $this->assertSame([
            'data.blocks.*.block → block:// (1)',
            'data.cover → media:// (1)',
            'data.gallery.* → medias:// (2)',
        ], $plain->locations);
    }

    public function test_media_references_by_id_under_the_media_key_break()
    {
        $this->seedReferences();

        $media = $this->finding($this->check(new StoredReferencesCheck, [LegacyArticle::class]), 'None of the 1 `media://` identifiers');

        $this->assertSame(Severity::Breaking, $media->severity);
        $this->assertStringContainsString('They look like ids.', $media->message);
        $this->assertStringContainsString('--from=media --to=medias', (string) $media->fix);
    }

    public function test_media_keys_are_checked_as_the_medias_module_will_map_them()
    {
        $this->seedReferences();
        JsonData::flush();
        config()->set('site.modules.medias', false);

        $findings = $this->check(new StoredReferencesCheck, [LegacyArticle::class]);

        $this->assertSame(Severity::Breaking, $this->finding($findings, '`media://` identifiers')->severity);
        $this->assertNull($this->finding($findings, '`medias://`', required: false));
    }

    public function test_unmapped_keys_fall_back_to_a_relation_of_the_same_name()
    {
        $this->seedReferences();

        $findings = $this->check(new StoredReferencesCheck, [LegacyArticle::class, CastArticle::class]);

        $this->assertSame(Severity::Info, $this->finding($findings, CastArticle::class.'::data` is cast with `JsonDataCast`')->severity);
        $this->assertSame(Severity::Info, $this->finding($findings, '`tag://` isn\'t mapped')->severity);
        $this->assertSame(Severity::Todo, $this->finding($findings, '`thing://` isn\'t mapped')->severity);
        $this->assertSame(Severity::Todo, $this->finding($findings, '`block://` isn\'t mapped')->severity);
    }

    public function test_medias_references_need_the_medias_reference_key()
    {
        $this->seedReferences();
        config()->set('site.medias.reference_key', 'media');

        $key = $this->finding($this->check(new StoredReferencesCheck, [LegacyArticle::class]), 'stores `medias://` references');

        $this->assertSame(Severity::Todo, $key->severity);
    }

    public function test_it_reports_the_blocks_to_convert()
    {
        Schema::create('blocks_pivot', function (Blueprint $table) {
            $table->unsignedBigInteger('block_id');
            $table->morphs('blockable');
        });
        DB::table('blocks_pivot')->insert([
            ['block_id' => 1, 'blockable_type' => 'page', 'blockable_id' => 1],
            ['block_id' => 2, 'blockable_type' => 'page', 'blockable_id' => 1],
        ]);

        $blocks = $this->finding($this->check(new BlocksCheck), '`blocks_pivot` links 2 blocks');

        $this->assertSame(Severity::Todo, $blocks->severity);
    }

    public function test_the_command_reports_and_fails_when_the_site_has_to_act()
    {
        $this->artisan('folklore:upgrade', ['--check' => true, '--path' => [self::SITE.'/app', self::SITE.'/config']])
            ->expectsOutputToContain('BREAKING')
            ->expectsOutputToContain('Panneau contracts')
            ->assertExitCode(1);
    }

    public function test_the_command_prints_json()
    {
        $this->artisan('folklore:upgrade', ['--check' => true, '--json' => true, '--path' => [self::SITE.'/app']])
            ->expectsOutputToContain('"severity": "breaking"')
            ->assertExitCode(1);
    }

    public function test_the_command_passes_a_site_with_nothing_to_do()
    {
        $this->turnModulesOff();

        $this->artisan('folklore:upgrade', ['--check' => true, '--path' => [self::SITE.'/missing']])
            ->assertExitCode(0);
    }

    public function test_the_command_warns_about_models_it_skips()
    {
        $this->artisan('folklore:upgrade', ['--check' => true, '--path' => [self::SITE.'/missing'], '--model' => [BrokenModel::class, Site::class]])
            ->expectsOutputToContain("doesn't load, and is skipped")
            ->expectsOutputToContain('is not an Eloquent model, and is skipped');
    }

    public function test_the_command_needs_check_until_the_upgrade_exists()
    {
        $this->artisan('folklore:upgrade')->assertExitCode(1);
    }

    /**
     * @return list<Media>
     */
    private function seedReferences(): array
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
        Schema::create('upgrade_cast_articles', function (Blueprint $table) {
            $table->id();
            $table->json('data')->nullable();
        });

        DB::table('upgrade_legacy_articles')->insert([
            'data' => json_encode([
                'cover' => 'media://'.$media[0]->getKey(),
                'gallery' => ['medias://'.$media[0]->getKey(), 'medias://'.$media[1]->getKey()],
                'link' => 'https://example.com',
                'blocks' => [['block' => 'block://3']],
            ]),
        ]);
        DB::table('upgrade_legacy_articles')->insert(['data' => null]);
        DB::table('upgrade_cast_articles')->insert([
            'data' => json_encode(['tag' => 'tag://5', 'thing' => 'thing://2']),
        ]);

        return $media;
    }

    private function turnModulesOff(): void
    {
        foreach (array_keys(ServiceProvider::MODULES) as $module) {
            config()->set('site.modules.'.$module, false);
        }
    }

    /**
     * @param  list<class-string<Model>>  $models
     * @return list<Finding>
     */
    private function check(Check $check, array $models = []): array
    {
        $site = new Site(self::SITE, $this->app['config'], $this->app['db'], Site::PATHS, $models);

        return array_values([...$check->check($site)]);
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function finding(array $findings, string $message, bool $required = true): ?Finding
    {
        foreach ($findings as $finding) {
            if (str_contains($finding->message, $message)) {
                return $finding;
            }
        }
        if ($required) {
            $this->fail("No finding says [{$message}]: ".implode(' | ', array_map(fn (Finding $finding) => $finding->message, $findings)));
        }

        return null;
    }
}
