<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Panneau\BlockTypes;
use Folklore\Tests\Feature\Pages\Fixtures\TextBlock as SiteTextBlock;
use Folklore\Tests\Feature\Panneau\Fixtures\Site\EditedPages;
use LogicException;
use Panneau\Fields\BlockType;
use Panneau\Fields\Field;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;

/**
 * The block types that the pages module's entity classes declare, in the `BlockTypes`
 * registry, and the helpers that change the fields of a standard resource
 * (docs/decisions/0025).
 */
class StandardBlockTypesTest extends StandardResourcesTestCase
{
    public function test_the_types_of_the_pages_module_that_declare_their_fields_come_first()
    {
        config(['site.pages.blocks.types' => ['image' => self::BLOCK_TYPES['image'], 'site' => SiteTextBlock::class, 'text' => self::BLOCK_TYPES['text']]]);
        $registry = $this->app->make(BlockTypes::class);
        $registry->register(fn () => [BlockType::make('stack', 'Stack', [Text::make('title')])]);

        // A type whose class doesn't declare its fields is registered by the site, as before
        $this->assertSame(['image', 'text', 'stack'], $registry->ids());
    }

    public function test_a_registered_type_replaces_a_declared_type_at_its_place()
    {
        $registry = $this->app->make(BlockTypes::class);
        $registry->register(BlockType::make('image', 'Picture', [Text::make('url')]));

        $types = $registry->all();

        $this->assertSame(array_keys(self::BLOCK_TYPES), $registry->ids());
        $this->assertSame(['url'], array_map(fn (Field $field) => $field->name(), $types[2]->getFields()));
        $registry->flush();
        $this->assertSame(array_keys(self::BLOCK_TYPES), $registry->ids());
        $this->assertSame(['image', 'caption', 'credits'], array_map(fn (Field $field) => $field->name(), $registry->all()[2]->getFields()));
    }

    public function test_the_labels_of_the_types_are_translated()
    {
        $this->app->setLocale('fr');

        $labels = array_map(fn (BlockType $type) => $type->toDefinition()['label'], $this->app->make(BlockTypes::class)->all());

        $this->assertSame(['Texte', 'Intertitre', 'Image', 'Galerie', 'Citation', 'Bouton', 'Intégration'], $labels);
    }

    public function test_fields_are_inserted_and_replaced_by_name()
    {
        $fields = $this->app->make(EditedPages::class)->fields();

        $inserted = EditedPages::before($fields, 'slug', Text::make('subtitle'), Text::make('kicker'));
        $this->assertSame(['title', 'subtitle', 'kicker', 'slug'], array_slice(array_map(fn (Field $field) => $field->name(), $inserted), 0, 4));

        $replaced = EditedPages::replace($fields, Text::make('description'));
        $this->assertNotInstanceOf(Textarea::class, $replaced[2]);
        $this->assertSame('description', $replaced[2]->name());
        $this->assertCount(count($fields), $replaced);
    }

    public function test_a_field_that_doesnt_exist_is_refused()
    {
        $this->expectException(LogicException::class);

        EditedPages::before($this->app->make(EditedPages::class)->fields(), 'subtitle', Text::make('kicker'));
    }
}
