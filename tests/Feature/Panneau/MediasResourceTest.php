<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Contracts\Repositories\Pages;
use Folklore\Tests\Feature\Panneau\Fixtures\Site\Images;
use Folklore\Tests\Feature\Panneau\Fixtures\Site\Medias;

/**
 * The standard media library (docs/decisions/0025), extended by an empty class of the site.
 */
class MediasResourceTest extends StandardResourcesTestCase
{
    private const PATH = '/panneau/api/resources/medias';

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('mediatheque.types.image.max_size', 5 * 1024 * 1024);
        $app['config']->set('mediatheque.types.video.max_size', 200 * 1024 * 1024);
        $app['config']->set('mediatheque.types.document.max_size', 20 * 1024 * 1024);
    }

    public function test_its_definition_is_a_library_of_images_videos_and_documents()
    {
        $definition = $this->resourceDefinition('medias');

        $this->assertSame('media', $definition['kind']);
        $this->assertSame('library', $definition['icon']);
        $this->assertSame('media', $definition['itemImage']);
        $this->assertSame(['gallery', 'table'], $definition['list']['layouts']);
        $this->assertTrue($definition['list']['search']);
        $this->assertSame(['image', 'video', 'document'], array_column($definition['list']['filters'][0]['options'], 'value'));
        $this->assertSame(['file', 'name'], array_column($definition['fields'], 'name'));
    }

    public function test_the_file_takes_the_largest_size_of_its_types()
    {
        $file = array_column($this->resourceDefinition('medias')['fields'], null, 'name')['file'];

        $this->assertContains(['rule' => 'maxSize', 'value' => 200 * 1024 * 1024], $file['rules']);
    }

    public function test_a_site_chooses_the_types_of_its_library()
    {
        $library = $this->app->make(Images::class);

        $this->assertSame(['image'], $library->mediaTypes());
        $this->assertContains(['rule' => 'maxSize', 'value' => 5 * 1024 * 1024], $library->fileField()->toDefinition()['rules']);
    }

    public function test_the_labels_are_translated()
    {
        $this->app->setLocale('fr');

        $library = $this->app->make(Medias::class);

        $this->assertSame('Médiathèque', $library->pluralLabel());
        $this->assertSame('Fichier', $library->fileField()->toDefinition()['label']);
    }

    public function test_the_list_has_the_media_with_their_thumbnails()
    {
        $image = $this->image('Portrait');

        $this->getJson(self::PATH)->assertOk()
            ->assertJsonPath('data.0.id', $image->uid)
            ->assertJsonPath('data.0.name', 'Portrait')
            ->assertJsonPath('data.0.media.type', 'image');
        $this->getJson(self::PATH.'?q=portrait')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_deleting_skips_a_media_that_a_page_uses()
    {
        $used = $this->image('Used');
        $unused = $this->image('Unused');
        $this->app->make(Pages::class)->create([
            'title' => ['fr' => 'Page'],
            'blocks' => [['type' => 'image', 'image' => ['id' => $used->uid]]],
        ]);

        $this->deleteJson(self::PATH.'/'.$used->uid)->assertStatus(409);
        $this->deleteJson(self::PATH.'/'.$unused->uid)->assertNoContent();
    }
}
