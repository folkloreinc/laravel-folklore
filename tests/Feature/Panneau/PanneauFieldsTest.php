<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Panneau\Fields\Block;
use Folklore\Panneau\Fields\Blocks;
use Folklore\Panneau\Fields\Page;
use Folklore\Panneau\Fields\PageSlug;
use Folklore\Panneau\Fields\PageSlugLocalized;
use Folklore\Panneau\Resources\Medias;
use Folklore\Tests\Feature\Panneau\Fixtures\BlocksResource;
use Folklore\Tests\Feature\Panneau\Fixtures\PagesResource;
use Folklore\Tests\Feature\Panneau\Fixtures\TextBlock;
use Folklore\Tests\TestCase;
use Panneau\ServiceProvider as PanneauServiceProvider;
use Panneau\Support\LocalizedField;

/**
 * Serializes the Panneau fields and resources of the package with
 * laravel-panneau, so that a Panneau release that breaks them fails here.
 * CI runs these tests with each supported Panneau release line.
 */
class PanneauFieldsTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('locale.locales', ['fr', 'en']);
        $app->setLocale('fr');
    }

    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [PanneauServiceProvider::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['panneau']->resources([BlocksResource::class, PagesResource::class, Medias::class]);
        $this->app['panneau']->routes();
    }

    protected function tearDown(): void
    {
        LocalizedField::setLocalesResolver(null);
        PageSlug::setRoutesResolver(fn () => []);

        parent::tearDown();
    }

    public function test_the_block_field_lists_the_blocks_resource()
    {
        $field = $this->serialize(Block::make('block'));

        $this->assertSame('block', $field['name']);
        $this->assertSame('object', $field['type']);
        $this->assertSame('resource-item', $field['component']);
        $this->assertSame('http://localhost/panneau/blocks', $field['requestUrl']);
        $this->assertSame('panneau.fields.select_block', $field['placeholder']);
        $this->assertSame('title', $field['itemLabelPath']);
        $this->assertNull($field['itemDescriptionPath']);
    }

    public function test_the_blocks_field_lists_the_block_types()
    {
        $field = $this->serialize(Blocks::make('blocks'));

        $this->assertSame('array', $field['type']);
        $this->assertSame('items', $field['component']);
        $this->assertTrue($field['withoutFormGroup']);
        $this->assertSame('panneau.fields.add_block', $field['addItemLabel']);
        $this->assertSame(['textBlock', 'columnsBlock'], array_column($field['types'], 'id'));
    }

    public function test_the_blocks_field_limits_the_depth_of_nested_blocks()
    {
        $twoLevels = $this->serialize(Blocks::make('blocks')->maxDepth(2));
        $this->assertSame(['textBlock', 'columnsBlock'], array_column($twoLevels['types'], 'id'));
        $nested = collect($twoLevels['types'][1]['fields'])->firstWhere('name', 'blocks');
        $this->assertSame(['textBlock'], array_column($nested['types'], 'id'));

        $oneLevel = $this->serialize(Blocks::make('blocks')->maxDepth(1));
        $this->assertSame(['textBlock'], array_column($oneLevel['types'], 'id'));
    }

    public function test_the_blocks_field_excludes_types()
    {
        $field = $this->serialize(Blocks::make('blocks')->withoutType(TextBlock::class));

        $this->assertSame(['columnsBlock'], array_column($field['types'], 'id'));
        $this->assertContains('blocks', array_column($field['types'][0]['fields'], 'name'));
    }

    public function test_the_page_field_queries_the_pages_resource()
    {
        $field = $this->serialize(Page::make('page')->withTypes('home', 'about'));

        $this->assertSame('item', $field['component']);
        $this->assertSame('http://localhost/panneau/pages', $field['requestUrl']);
        $this->assertSame('title.fr', $field['itemLabelPath']);
        $this->assertSame(['paginated' => false, 'type' => ['home', 'about']], $field['requestQuery']);
    }

    public function test_the_page_slug_field_resolves_the_routes_of_its_locale()
    {
        PageSlug::setRoutesResolver(fn ($locale) => ['page' => '/'.$locale.'/{slug}']);

        $field = $this->serialize(PageSlug::make('en'));
        $this->assertSame('page-slug', $field['component']);
        $this->assertSame(['page' => '/en/{slug}'], $field['routes']);

        $this->assertSame(['page' => '/fr/{slug}'], $this->serialize(PageSlug::make('slug'))['routes']);
    }

    public function test_the_localized_page_slug_field_has_a_page_slug_field_per_locale()
    {
        LocalizedField::setLocalesResolver(fn () => ['fr', 'en']);

        $field = $this->serialize(PageSlugLocalized::make('slug')->isDisabled());

        $this->assertSame('page-slug-localized', $field['component']);
        $this->assertSame(['fr', 'en'], $field['locales']);
        $this->assertSame(['fr', 'en'], array_keys($field['properties']));
        $this->assertSame('page-slug', $field['properties']['en']['component']);
        $this->assertSame('en', $field['properties']['en']['name']);
        $this->assertTrue($field['properties']['en']['disabled']);
    }

    public function test_the_medias_resource_is_registered()
    {
        $resource = $this->app['panneau']->resource('medias');
        $this->assertInstanceOf(Medias::class, $resource);

        $data = json_decode(json_encode($resource->toArray()), true);

        $this->assertSame('medias', $data['id']);
        $this->assertSame(['name'], array_column($data['fields'], 'name'));
        $this->assertFalse($data['settings']['canCreate']);
        $this->assertSame('thumbnail', $data['index']['columns'][0]['id']);
    }

    protected function serialize($field): array
    {
        return json_decode(json_encode($field->toArray()), true);
    }
}
