<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Pages\PageRoutes;
use Folklore\Panneau\BlockTypes;
use Folklore\Panneau\Fields\Blocks;
use Folklore\Panneau\Fields\Page;
use Folklore\Panneau\Fields\Pages;
use Folklore\Panneau\Fields\PageSlug;
use Folklore\Panneau\PanneauServiceProvider;
use Folklore\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use LogicException;
use Panneau\Fields\BlockType;
use Panneau\Fields\Text;
use Panneau\Forms\FieldCheck;

/**
 * The definitions of the page fields of the panneau module.
 */
class PageFieldsTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [...parent::getPackageProviders($app), PanneauServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(BlockTypes::class)->register(fn () => [
            BlockType::make('text', 'Text', [Text::make('body')]),
            BlockType::make('image', 'Image', [Text::make('caption')]),
            BlockType::make('stack', 'Stack', [Blocks::make('blocks')]),
        ]);
    }

    /**
     * The ids of the types of a definition of blocks, with those of their nested blocks.
     *
     * @param  array<string, mixed>  $definition
     * @return array<array-key, mixed>
     */
    private static function typesOf(array $definition): array
    {
        $types = [];
        foreach ($definition['types'] ?? [] as $type) {
            $nested = array_values(array_filter($type['fields'], fn (array $field): bool => $field['type'] === 'repeater'));
            $types[$type['id']] = count($nested) > 0 ? self::typesOf($nested[0]) : true;
        }

        return $types;
    }

    public function test_blocks_offer_the_types_of_the_registry()
    {
        $definition = Blocks::make('blocks')->label('Blocks')->toDefinition();

        $this->assertSame('repeater', $definition['type']);
        $this->assertSame('Blocks', $definition['label']);
        $this->assertSame(['text', 'image', 'stack'], array_column($definition['types'], 'id'));
        $this->assertSame([['name' => 'body', 'type' => 'text']], $definition['types'][0]['fields']);
    }

    public function test_a_type_registered_again_replaces_the_previous_one()
    {
        $this->app->make(BlockTypes::class)->register(BlockType::make('text', 'Paragraph', [Text::make('body')]));

        $this->assertSame(['text', 'image', 'stack'], $this->app->make(BlockTypes::class)->ids());
        $this->assertSame('Paragraph', Blocks::make('blocks')->toDefinition()['types'][0]['label']);
    }

    public function test_blocks_are_limited_to_some_types()
    {
        $this->assertSame(['text'], array_keys(self::typesOf(Blocks::make('blocks')->onlyTypes('text', 'stack')->maxDepth(1)->toDefinition())));
        $this->assertSame(['image'], array_map(fn (BlockType $type): string => $type->id(), Blocks::make('blocks')->exceptTypes(['text', 'stack'])->blockTypes()));
        $this->assertSame(['image', 'stack'], array_map(fn (BlockType $type): string => $type->id(), Blocks::make('blocks')->onlyTypes(['image', 'stack'])->exceptTypes('text')->blockTypes()));
    }

    public function test_the_types_given_to_the_field_replace_those_of_the_registry()
    {
        $blocks = Blocks::make('blocks')->types([BlockType::make('quote', 'Quote', [Text::make('quote')])]);

        $this->assertSame(['quote'], array_column($blocks->toDefinition()['types'], 'id'));
    }

    public function test_blocks_nest_three_levels_by_default()
    {
        $this->assertSame([
            'text' => true,
            'image' => true,
            'stack' => [
                'text' => true,
                'image' => true,
                'stack' => ['text' => true, 'image' => true],
            ],
        ], self::typesOf(Blocks::make('blocks')->toDefinition()));
    }

    public function test_the_maximum_depth_removes_the_types_whose_blocks_would_be_deeper()
    {
        $this->assertSame(['text' => true, 'image' => true], self::typesOf(Blocks::make('blocks')->maxDepth(1)->toDefinition()));
        $this->assertSame(
            ['text' => true, 'image' => true, 'stack' => ['text' => true, 'image' => true]],
            self::typesOf(Blocks::make('blocks')->maxDepth(2)->toDefinition()),
        );
    }

    public function test_the_smallest_maximum_depth_of_the_nested_fields_applies()
    {
        $blocks = Blocks::make('blocks')->maxDepth(5)->types([
            BlockType::make('text', 'Text', [Text::make('body')]),
            BlockType::make('columns', 'Columns', [Blocks::make('columns')->maxDepth(2)->exceptTypes('columns')->types([
                BlockType::make('text', 'Text', [Text::make('body')]),
                BlockType::make('stack', 'Stack', [Blocks::make('blocks')]),
            ])]),
        ]);

        // The stack would put blocks at the third level, under the limit of the columns
        $this->assertSame(['text' => true, 'columns' => ['text' => true]], self::typesOf($blocks->toDefinition()));
        $nested = $blocks->blockTypes()[1]->getFields()[0];
        $this->assertInstanceOf(Blocks::class, $nested);
        $this->assertSame(2, $nested->level());
        $this->assertSame(2, $nested->depthLimit());
    }

    public function test_the_maximum_depth_is_at_least_one()
    {
        $this->expectException(LogicException::class);

        Blocks::make('blocks')->maxDepth(0);
    }

    public function test_page_and_pages_relate_to_the_pages_resource()
    {
        $this->assertSame(['name' => 'section', 'type' => 'relation', 'resource' => 'pages'], Page::make('section')->toDefinition());
        $this->assertSame(['name' => 'sections', 'type' => 'relation', 'resource' => 'pages', 'multiple' => true], Pages::make('sections')->toDefinition());
        $this->assertSame('sections', Page::make('section')->resource('sections')->relatedResource());
    }

    public function test_the_page_slug_is_a_localized_slug_that_checks_its_url()
    {
        $this->assertSame(
            ['name' => 'slug', 'type' => 'slug', 'localized' => true, 'from' => 'title', 'check' => true],
            PageSlug::make('slug')->toDefinition(),
        );
        $this->assertSame(
            ['name' => 'slug', 'type' => 'slug', 'from' => 'name', 'check' => true],
            PageSlug::make('slug')->localized(false)->from('name')->toDefinition(),
        );
    }

    public function test_the_routes_of_pages_are_named_by_the_config()
    {
        $this->app['config']->set('site.pages.routes.page', 'pages.show');
        Route::get('fr/pages/{page}', fn () => '')->name('fr.pages.show');
        Route::get('fr/{parent}/{page}', fn () => '')->name('fr.page_with_parent');
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertSame('pages.show', PageRoutes::name('page'));
        $this->assertSame('home', PageRoutes::name('home'));
        $this->assertSame(url('fr/pages/ete'), PageRoutes::page('ete', 'fr'));
        $this->assertSame(url('fr/vacances/ete'), PageRoutes::page('ete', 'fr', 'vacances'));
        $this->assertNull(PageRoutes::page('summer', 'en'));
        $this->assertNull(PageRoutes::home('fr'));
    }

    public function test_the_page_slug_without_a_parent_field_ignores_the_parent()
    {
        Route::get('fr/{page}', fn () => '')->name('fr.page');
        $this->app['router']->getRoutes()->refreshNameLookups();
        $check = fn (PageSlug $slug): array => $slug->check(new FieldCheck('ete', 'fr', null, ['parent' => '12'], fn (): bool => false));

        $this->assertSame(url('fr/ete'), $check(PageSlug::make('slug')->parentField(null))['url']);
    }
}
