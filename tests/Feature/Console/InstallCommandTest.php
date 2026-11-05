<?php

namespace Folklore\Tests\Feature\Console;

use Folklore\ServiceProvider as FolkloreServiceProvider;
use Folklore\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use ReflectionProperty;

/**
 * `folklore:install` writes the classes of the standard resources in a site, and wires them
 * (docs/decisions/0025).
 */
class InstallCommandTest extends TestCase
{
    private const NAMESPACE = 'InstallSite\\';

    private string $basePath;

    private string $originalBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir().'/folklore-install-'.uniqid();
        File::makeDirectory($this->basePath.'/bootstrap', 0755, true);
        File::put($this->basePath.'/bootstrap/providers.php', "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n];\n");
        $this->originalBasePath = $this->app->basePath();
        $this->app->setBasePath($this->basePath);
        $this->app->useAppPath($this->basePath.'/app');
        $this->app->useConfigPath($this->basePath.'/config');
        // Laravel reads the application's namespace from its composer.json, which a
        // temporary path doesn't have
        (new ReflectionProperty($this->app, 'namespace'))->setValue($this->app, self::NAMESPACE);
        // A new site: its modules are off
        foreach (['pages', 'auth', 'medias', 'panneau'] as $module) {
            config(['site.modules.'.$module => false]);
        }
        config(['site.pages.blocks.types' => [], 'site.repositories' => []]);
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->basePath);

        parent::tearDown();
    }

    private function file(string $path): string
    {
        return (string) file_get_contents($this->basePath.'/'.$path);
    }

    /**
     * The files of the site, with their contents.
     *
     * @return array<string, string>
     */
    private function files(): array
    {
        $files = [];
        foreach (File::allFiles($this->basePath) as $file) {
            $files[$file->getRelativePathname()] = $file->getContents();
        }
        ksort($files);

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function siteConfig(): array
    {
        return require $this->basePath.'/config/site.php';
    }

    public function test_it_writes_the_classes_of_the_standard_resources_in_an_empty_site()
    {
        $this->artisan('folklore:install')->assertSuccessful();

        $expected = [
            'app/Contracts/Entities/Page.php' => 'interface Page extends \\Folklore\\Contracts\\Entities\\Page',
            'app/Entities/Page.php' => 'class Page extends \\Folklore\\Entities\\Page implements \\InstallSite\\Contracts\\Entities\\Page',
            'app/Models/Page.php' => 'return $this->toTypedEntity() ?? new \\InstallSite\\Entities\\Page($this);',
            'app/Contracts/Repositories/Pages.php' => 'interface Pages extends \\Folklore\\Contracts\\Repositories\\Pages',
            'app/Repositories/Pages.php' => 'return new \\InstallSite\\Models\\Page;',
            'app/Panneau/Resources/Pages.php' => 'class Pages extends \\Folklore\\Panneau\\Resources\\Pages',
            'app/Entities/Blocks/TextBlock.php' => 'class TextBlock extends \\Folklore\\Entities\\Blocks\\TextBlock',
            'app/Entities/Blocks/EmbedBlock.php' => 'class EmbedBlock extends \\Folklore\\Entities\\Blocks\\EmbedBlock',
            'app/Contracts/Entities/User.php' => 'interface User extends \\Folklore\\Contracts\\Entities\\User',
            'app/Entities/User.php' => 'class User extends \\Folklore\\Entities\\User implements \\InstallSite\\Contracts\\Entities\\User',
            'app/Models/User.php' => 'return new \\InstallSite\\Entities\\User($this);',
            'app/Repositories/Users.php' => 'protected function newModel(): \\Illuminate\\Database\\Eloquent\\Model',
            'app/Panneau/Resources/Users.php' => 'class Users extends \\Folklore\\Panneau\\Resources\\Users',
            'app/Panneau/Resources/Medias.php' => 'class Medias extends \\Folklore\\Panneau\\Resources\\Medias',
            'app/Providers/PanneauServiceProvider.php' => '\\InstallSite\\Panneau\\Resources\\Medias::class,',
        ];
        foreach ($expected as $path => $code) {
            $this->assertStringContainsString($code, $this->file($path), $path);
        }
        $this->assertCount(7, File::files($this->basePath.'/app/Entities/Blocks'));

        // The classes load, and extend the package's
        foreach ([
            'Contracts/Entities/Page', 'Entities/Page', 'Models/Page', 'Contracts/Repositories/Pages', 'Repositories/Pages', 'Panneau/Resources/Pages',
            'Contracts/Entities/User', 'Entities/User', 'Models/User', 'Contracts/Repositories/Users', 'Repositories/Users', 'Panneau/Resources/Users',
            'Panneau/Resources/Medias', 'Entities/Blocks/ImageBlock', 'Providers/PanneauServiceProvider',
        ] as $file) {
            require_once $this->basePath.'/app/'.$file.'.php';
        }
        $this->assertTrue(is_subclass_of('InstallSite\\Repositories\\Pages', 'Folklore\\Repositories\\Pages'));
        $this->assertTrue(is_subclass_of('InstallSite\\Repositories\\Pages', 'InstallSite\\Contracts\\Repositories\\Pages'));
        $this->assertTrue(is_subclass_of('InstallSite\\Models\\User', 'Folklore\\Models\\User'));
        $this->assertTrue(is_subclass_of('InstallSite\\Entities\\Blocks\\ImageBlock', 'Folklore\\Entities\\Blocks\\ImageBlock'));
        $this->assertTrue(is_subclass_of('InstallSite\\Panneau\\Resources\\Medias', 'Folklore\\Panneau\\Resources\\Medias'));
        $this->assertTrue(is_subclass_of('InstallSite\\Providers\\PanneauServiceProvider', 'Illuminate\\Support\\ServiceProvider'));
    }

    public function test_it_wires_the_classes_in_the_config_and_the_providers()
    {
        $this->artisan('folklore:install')->assertSuccessful();

        $config = $this->siteConfig();
        foreach (['pages', 'auth', 'medias', 'panneau'] as $module) {
            $this->assertTrue($config['modules'][$module], $module);
        }
        $this->assertSame('InstallSite\\Models\\Page', $config['pages']['model']);
        $this->assertSame(
            ['text', 'heading', 'image', 'gallery', 'quote', 'button', 'embed'],
            array_keys($config['pages']['blocks']['types']),
        );
        $this->assertSame('InstallSite\\Entities\\Blocks\\GalleryBlock', $config['pages']['blocks']['types']['gallery']);
        $this->assertSame([
            'InstallSite\\Contracts\\Repositories\\Pages' => 'InstallSite\\Repositories\\Pages',
            'Folklore\\Contracts\\Repositories\\Pages' => 'InstallSite\\Repositories\\Pages',
            'InstallSite\\Contracts\\Repositories\\Users' => 'InstallSite\\Repositories\\Users',
            'Folklore\\Contracts\\Repositories\\Users' => 'InstallSite\\Repositories\\Users',
        ], $config['repositories']);
        // The rest of the package's config is there
        $this->assertSame('media', $config['medias']['reference_key']);

        $provider = $this->file('app/Providers/PanneauServiceProvider.php');
        $this->assertStringContainsString('Gate::policy(\\Folklore\\Contracts\\Entities\\Page::class, \\Folklore\\Panneau\\Policies\\PagePolicy::class);', $provider);
        $this->assertStringContainsString('Gate::policy(\\Folklore\\Mediatheque\\Support\\Models::media(), \\Folklore\\Panneau\\Policies\\MediaPolicy::class);', $provider);
        $this->assertStringContainsString("App\\Providers\\AppServiceProvider::class,\n    InstallSite\\Providers\\PanneauServiceProvider::class,\n];", $this->file('bootstrap/providers.php'));
    }

    public function test_running_it_again_changes_nothing()
    {
        $this->artisan('folklore:install')->assertSuccessful();
        $files = $this->files();

        $this->artisan('folklore:install')
            ->expectsOutputToContain('exists')
            ->assertSuccessful();

        $this->assertSame($files, $this->files());
    }

    public function test_a_dry_run_writes_nothing()
    {
        $files = $this->files();

        $this->artisan('folklore:install', ['--dry-run' => true])
            ->expectsOutputToContain('would be created')
            ->assertSuccessful();

        $this->assertSame($files, $this->files());
    }

    public function test_it_installs_some_resources_then_adds_others()
    {
        $this->artisan('folklore:install', ['--only' => 'medias'])->assertSuccessful();

        $this->assertFileExists($this->basePath.'/app/Panneau/Resources/Medias.php');
        $this->assertFileDoesNotExist($this->basePath.'/app/Panneau/Resources/Pages.php');
        $this->assertArrayNotHasKey('pages', $this->siteConfig()['modules'] === [] ? [] : array_filter($this->siteConfig()['modules']));

        $this->artisan('folklore:install', ['--only' => 'pages'])->assertSuccessful();

        $provider = $this->file('app/Providers/PanneauServiceProvider.php');
        $this->assertStringContainsString('\\InstallSite\\Panneau\\Resources\\Pages::class,', $provider);
        $this->assertStringContainsString('\\InstallSite\\Panneau\\Resources\\Medias::class,', $provider);
        $this->assertStringContainsString('PagePolicy', $provider);
        $this->assertStringNotContainsString('UserPolicy', $provider);
        $this->assertSame(1, substr_count($this->file('bootstrap/providers.php'), 'PanneauServiceProvider'));
        $this->assertTrue($this->siteConfig()['modules']['pages']);
        $this->assertFileDoesNotExist($this->basePath.'/app/Panneau/Resources/Users.php');
    }

    public function test_it_registers_the_resources_in_the_sites_own_provider()
    {
        File::ensureDirectoryExists($this->basePath.'/app/Providers');
        File::put($this->basePath.'/app/Providers/PanneauServiceProvider.php', <<<'PHP'
            <?php

            namespace InstallSite\Providers;

            use InstallSite\Panneau\Resources\Orders;
            use Illuminate\Support\ServiceProvider;
            use Panneau\Support\Facade as Panneau;

            class PanneauServiceProvider extends ServiceProvider
            {
                public function boot(): void
                {
                    Panneau::resources([Orders::class]);
                }
            }
            PHP);

        $this->artisan('folklore:install', ['--only' => 'pages,users'])->assertSuccessful();

        $provider = $this->file('app/Providers/PanneauServiceProvider.php');
        $this->assertStringContainsString("Panneau::resources([\n            \\InstallSite\\Panneau\\Resources\\Users::class,\n            \\InstallSite\\Panneau\\Resources\\Pages::class,\n            Orders::class]);", $provider);
        $this->assertStringNotContainsString('InstallSite\\Providers\\PanneauServiceProvider', $this->file('bootstrap/providers.php'));
    }

    public function test_it_reports_the_sites_classes_that_dont_extend_the_packages_without_rewriting_them()
    {
        // A namespace of its own: the classes that another test loads stay loaded
        (new ReflectionProperty($this->app, 'namespace'))->setValue($this->app, 'ReportSite\\');
        $model = <<<'PHP'
            <?php

            namespace ReportSite\Models;

            use Illuminate\Foundation\Auth\User as Authenticatable;

            class User extends Authenticatable
            {
            }
            PHP;
        $entity = <<<'PHP'
            <?php

            namespace ReportSite\Entities;

            use Folklore\Entities\Page as BasePage;

            class Page extends BasePage
            {
            }
            PHP;
        File::ensureDirectoryExists($this->basePath.'/app/Models');
        File::ensureDirectoryExists($this->basePath.'/app/Entities');
        File::put($this->basePath.'/app/Models/User.php', $model);
        File::put($this->basePath.'/app/Entities/Page.php', $entity);

        $this->artisan('folklore:install')
            ->expectsOutputToContain('ReportSite\\Models\\User doesn\'t extend Folklore\\Models\\User yet')
            ->doesntExpectOutputToContain('ReportSite\\Entities\\Page doesn\'t extend')
            ->assertSuccessful();

        $this->assertSame($model, $this->file('app/Models/User.php'));
        $this->assertSame($entity, $this->file('app/Entities/Page.php'));
    }

    public function test_it_keeps_the_values_that_the_site_set()
    {
        File::ensureDirectoryExists($this->basePath.'/config');
        File::put($this->basePath.'/config/site.php', <<<'PHP'
            <?php

            return [
                'pages' => [
                    'blocks' => [
                        'types' => [
                            'text' => \InstallSite\Entities\Blocks\Paragraph::class,
                        ],
                    ],
                ],
                'modules' => [
                    'pages' => true,
                ],
            ];
            PHP);
        config(['site.pages.blocks.types' => ['text' => 'InstallSite\\Entities\\Blocks\\Paragraph'], 'site.modules.pages' => true]);

        $this->artisan('folklore:install', ['--only' => 'pages'])->assertSuccessful();

        $config = $this->siteConfig();
        $this->assertSame('InstallSite\\Entities\\Blocks\\Paragraph', $config['pages']['blocks']['types']['text']);
        $this->assertSame('InstallSite\\Entities\\Blocks\\ImageBlock', $config['pages']['blocks']['types']['image']);
        $this->assertTrue($config['modules']['panneau']);
    }

    public function test_force_overwrites_the_classes()
    {
        File::ensureDirectoryExists($this->basePath.'/app/Panneau/Resources');
        File::put($this->basePath.'/app/Panneau/Resources/Medias.php', '<?php // the site\'s');

        $this->artisan('folklore:install', ['--only' => 'medias', '--force' => true])
            ->expectsOutputToContain('overwritten')
            ->assertSuccessful();

        $this->assertStringContainsString('class Medias extends \\Folklore\\Panneau\\Resources\\Medias', $this->file('app/Panneau/Resources/Medias.php'));
    }

    public function test_an_unknown_resource_is_refused()
    {
        $this->artisan('folklore:install', ['--only' => 'pages,orders'])
            ->expectsOutputToContain('Unknown resource: orders')
            ->assertFailed();

        $this->assertSame(['bootstrap/providers.php'], array_keys($this->files()));
    }

    public function test_the_stubs_are_publishable_and_the_sites_are_used()
    {
        $published = ServiceProvider::pathsToPublish(FolkloreServiceProvider::class, 'folklore-stubs');

        $this->assertSame($this->originalBasePath.'/stubs/folklore/install', $published[realpath(__DIR__.'/../../../src/Folklore').'/../stubs/install'] ?? null);
        $this->assertContains($this->originalBasePath.'/stubs/entity-contract.stub', $published);

        File::ensureDirectoryExists($this->basePath.'/stubs/folklore/install');
        File::put($this->basePath.'/stubs/folklore/install/class.stub', "<?php\n\nnamespace {{ namespace }};\n\n// The site's stub\nclass {{ class }} extends \\{{ base }}{{ implements }}\n{\n}\n");
        $this->artisan('folklore:install', ['--only' => 'medias'])->assertSuccessful();

        $this->assertStringContainsString("// The site's stub", $this->file('app/Panneau/Resources/Medias.php'));
    }
}
