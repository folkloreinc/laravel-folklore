<?php

namespace Folklore\Tests\Feature\Pages;

use Folklore\Eloquent\MediaUsagesSync;
use Folklore\Entities\Block;
use Folklore\JsonData\Schema\Compiler;
use Folklore\JsonData\Schema\Json;
use Folklore\Models\Page;
use Folklore\Pages\BlockRegistry;
use Folklore\Tests\Feature\Eloquent\JsonDataTestCase;
use Folklore\Tests\Feature\Pages\Fixtures\StackBlock;
use Folklore\Tests\Feature\Pages\Fixtures\TextBlock;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * The block types of the pages module, `site.pages.blocks` (docs/decisions/0023): the union
 * of a page's blocks, their entities and their ids.
 */
class BlockRegistryTest extends JsonDataTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'site.locales' => ['fr', 'en'],
            'app.locale' => 'fr',
            'site.pages.blocks.types' => ['text' => TextBlock::class, 'stack' => StackBlock::class],
            'site.pages.blocks.depth' => 2,
        ]);
    }

    private function registry(): BlockRegistry
    {
        return $this->app->make(BlockRegistry::class);
    }

    public function test_the_union_of_a_level_has_its_types_with_an_id_and_a_handle()
    {
        $union = $this->registry()->union();

        $this->assertSame(['text', 'stack'], array_keys($union->getVariants()));
        $this->assertSame(['id', 'handle', 'body', 'image'], array_keys($union->getVariant('text')->getProperties()));
        $this->assertFalse($union->getVariant('text')->allowsAdditional());
    }

    public function test_a_type_that_holds_blocks_is_not_offered_at_the_deepest_level()
    {
        $this->assertSame(['text'], array_keys($this->registry()->union(2)->getVariants()));
        $this->assertSame(
            ['blocks.*.blocks.*.image', 'blocks.*.image', 'image', 'parent'],
            collect(Compiler::compile((new Page)->jsonDataSchema('data'))['references'])->pluck('path')->sort()->values()->all(),
        );
    }

    public function test_a_type_must_be_a_block_entity()
    {
        config(['site.pages.blocks.types' => ['text' => \stdClass::class]]);

        $this->expectException(InvalidArgumentException::class);
        $this->registry()->types();
    }

    public function test_blocks_are_read_as_entities_of_their_types()
    {
        $blocks = $this->registry()->toEntities([
            ['id' => 'one', 'type' => 'text', 'body' => 'First'],
            ['id' => 'two', 'type' => 'stack', 'blocks' => [['id' => 'three', 'type' => 'text', 'body' => 'Nested']]],
            ['id' => 'four', 'type' => 'unknown'],
            'not a block',
        ]);

        $this->assertCount(3, $blocks);
        $this->assertInstanceOf(TextBlock::class, $blocks[0]);
        $this->assertInstanceOf(StackBlock::class, $blocks[1]);
        $this->assertSame('Nested', $blocks[1]->blocks()[0]->body());
        $this->assertSame(Block::class, $blocks[2]::class);
        $this->assertSame('unknown', $blocks[2]->type());
    }

    public function test_every_block_gets_an_id_once()
    {
        $blocks = $this->registry()->withIds([
            ['type' => 'text'],
            ['id' => 12, 'type' => 'stack', 'blocks' => [['type' => 'text']]],
        ]);

        $this->assertMatchesRegularExpression('/^blk_[0-9a-z]{26}$/', $blocks[0]['id']);
        $this->assertSame('id', array_key_first($blocks[0]));
        $this->assertSame('12', $blocks[1]['id']);
        $this->assertMatchesRegularExpression('/^blk_/', $blocks[1]['blocks'][0]['id']);
        $this->assertSame($blocks, $this->registry()->withIds($blocks));
    }

    public function test_the_media_of_blocks_are_synced_to_the_page()
    {
        $image = $this->makeMedia();
        $nested = $this->makeMedia();

        $page = Page::create(['handle' => 'page', 'data' => ['blocks' => [
            ['id' => 'one', 'type' => 'text', 'image' => $image],
            ['id' => 'two', 'type' => 'stack', 'blocks' => [['id' => 'three', 'type' => 'text', 'image' => $nested]]],
        ]]]);

        $this->assertEqualsCanonicalizing([$image->id, $nested->id], $page->medias(MediaUsagesSync::HANDLE)->pluck('id')->all());
        $blocks = Page::query()->findOrFail($page->id)->toEntity()->blocks();
        $this->assertTrue($blocks[1]->blocks()[0]->data()['image']->is($nested));
    }

    public function test_the_rules_of_a_page_check_each_block_with_its_type()
    {
        $validator = Validator::make(
            ['blocks' => [['type' => 'text', 'body' => 5], ['type' => 'stack', 'blocks' => [['type' => 'stack']]], ['type' => 'video']]],
            Json::rules(new Page, 'data'),
        );

        $this->assertSame(['blocks.0.body', 'blocks.1.blocks.0', 'blocks.2'], collect($validator->errors()->keys())->sort()->values()->all());
    }
}
