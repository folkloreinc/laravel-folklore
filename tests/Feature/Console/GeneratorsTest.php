<?php

namespace Folklore\Tests\Feature\Console;

use Folklore\Contracts\Repositories\Entities as EntitiesContract;
use Folklore\Entities\ModelEntity;
use Folklore\Repositories\Entities;
use Folklore\Tests\Feature\Repositories\Fixtures\Note;
use Folklore\Tests\TestCase;
use Illuminate\Support\Facades\File;
use ReflectionProperty;

/**
 * `make:entity` generates an entity, its contract, a repository and its contract, on the
 * 2.0 bases.
 */
class GeneratorsTest extends TestCase
{
    private string $appPath;

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        // The generators are registered in the local environment
        $app->detectEnvironment(fn () => 'local');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->appPath = sys_get_temp_dir().'/folklore-generators-'.uniqid();
        File::makeDirectory($this->appPath);
        $this->app->useAppPath($this->appPath);
        // Laravel reads the application's namespace from its composer.json, which a
        // temporary path doesn't have
        (new ReflectionProperty($this->app, 'namespace'))->setValue($this->app, 'App\\');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->appPath);

        parent::tearDown();
    }

    public function test_make_entity_generates_classes_on_the_2_0_bases()
    {
        $this->artisan('make:entity', ['name' => 'Note', '--model' => '\\App\\Models\\Note'])->assertSuccessful();

        $entity = (string) file_get_contents($this->appPath.'/Entities/Note.php');
        $repository = (string) file_get_contents($this->appPath.'/Repositories/Notes.php');
        $contract = (string) file_get_contents($this->appPath.'/Contracts/Repositories/Notes.php');
        $this->assertStringContainsString('class Note extends ModelEntity implements', $entity);
        $this->assertStringContainsString('@extends ModelEntity<', $entity);
        $this->assertStringContainsString('@extends Entities<', $repository);
        $this->assertStringContainsString('@extends Entities<', $contract);

        // The generated classes load: the model is a fixture with the same name
        if (! class_exists('App\\Models\\Note', false)) {
            class_alias(Note::class, 'App\\Models\\Note');
        }
        foreach (['Contracts/Entities/Note.php', 'Entities/Note.php', 'Contracts/Repositories/Notes.php', 'Repositories/Notes.php'] as $file) {
            require_once $this->appPath.'/'.$file;
        }
        $this->assertTrue(is_subclass_of('App\\Entities\\Note', ModelEntity::class));
        $this->assertTrue(is_subclass_of('App\\Repositories\\Notes', Entities::class));
        $this->assertTrue(is_subclass_of('App\\Repositories\\Notes', 'App\\Contracts\\Repositories\\Notes'));
        $this->assertTrue(is_subclass_of('App\\Contracts\\Repositories\\Notes', EntitiesContract::class));
    }
}
