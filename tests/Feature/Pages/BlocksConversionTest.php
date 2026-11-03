<?php

namespace Folklore\Tests\Feature\Pages;

use Folklore\Eloquent\MediaUsagesSync;
use Folklore\Models\Page;
use Folklore\Tests\Feature\Eloquent\JsonDataTestCase;
use Folklore\Tests\Feature\Pages\Fixtures\StackBlock;
use Folklore\Tests\Feature\Pages\Fixtures\TextBlock;
use Folklore\Upgrade\Site;
use Folklore\Upgrade\Upgrade;
use Illuminate\Support\Facades\DB;

/**
 * `folklore:upgrade` converts the blocks of 1.x pages, rows of `blocks` linked through
 * `blocks_pivot`, into the pages' JSON, and their parents into `page://` (decisions/0023).
 */
class BlocksConversionTest extends JsonDataTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['site.pages.blocks.types' => ['text' => TextBlock::class, 'stack' => StackBlock::class]]);
    }

    private function site(): Site
    {
        return new Site(base_path(), $this->app['config'], $this->app['db']);
    }

    /**
     * A 1.x page and its blocks, as 1.x stored them: a stack that holds a text block with
     * an image, a handled text block, and a reference to a block that doesn't exist.
     *
     * @return array{page: int, parent: int, media: int, blocks: list<int>}
     */
    private function legacyPage(): array
    {
        $media = $this->makeMedia();
        $parent = DB::table('pages')->insertGetId(['handle' => 'parent', 'data' => json_encode(['title' => ['fr' => 'Parent']])]);
        $text = DB::table('blocks')->insertGetId(['type' => 'text', 'data' => json_encode(['body' => 'Nested', 'image' => 'medias://'.$media->id])]);
        $stack = DB::table('blocks')->insertGetId(['type' => 'stack', 'data' => json_encode(['title' => ['fr' => 'Pile'], 'blocks' => ['blocks://'.$text]])]);
        $intro = DB::table('blocks')->insertGetId(['type' => 'text', 'handle' => 'intro', 'data' => json_encode(['body' => 'Intro'])]);
        $page = DB::table('pages')->insertGetId(['handle' => 'page', 'parent_id' => $parent, 'data' => json_encode([
            'title' => ['fr' => 'Page'],
            'parent' => 'parent://'.$parent,
            'blocks' => ['blocks://'.$intro, 'blocks://'.$stack, 'blocks://999'],
        ])]);
        DB::table('blocks_pivot')->insert([
            ['block_id' => $intro, 'blockable_type' => Page::class, 'blockable_id' => $page],
            ['block_id' => $stack, 'blockable_type' => Page::class, 'blockable_id' => $page],
            ['block_id' => $text, 'blockable_type' => 'Folklore\\Models\\Block', 'blockable_id' => $stack],
            ['block_id' => $intro, 'blockable_type' => 'App\\Models\\Article', 'blockable_id' => 1],
        ]);

        return ['page' => $page, 'parent' => $parent, 'media' => $media->id, 'blocks' => [$intro, $stack, $text]];
    }

    public function test_the_dry_run_counts_what_it_would_convert_and_writes_nothing()
    {
        $ids = $this->legacyPage();
        $before = DB::table('pages')->where('id', $ids['page'])->value('data');

        $conversion = (new Upgrade)->convertBlocks($this->site(), false);

        $this->assertSame(1, $conversion->pages);
        $this->assertSame(3, $conversion->blocks);
        $this->assertSame(1, $conversion->parents);
        $this->assertSame(1, $conversion->missing);
        // The block of another model: its row of blocks_pivot isn't one of a page's blocks
        $this->assertSame(1, $conversion->left);
        $this->assertSame($before, DB::table('pages')->where('id', $ids['page'])->value('data'));
    }

    public function test_the_blocks_are_put_into_their_page_and_their_media_synced_to_it()
    {
        $ids = $this->legacyPage();
        [$intro, $stack, $text] = $ids['blocks'];

        (new Upgrade)->convertBlocks($this->site(), true);

        $data = json_decode((string) DB::table('pages')->where('id', $ids['page'])->value('data'), true);
        $this->assertSame('page://'.$ids['parent'], $data['parent']);
        $this->assertSame([
            ['id' => (string) $intro, 'type' => 'text', 'handle' => 'intro', 'body' => 'Intro'],
            ['id' => (string) $stack, 'type' => 'stack', 'title' => ['fr' => 'Pile'], 'blocks' => [
                ['id' => (string) $text, 'type' => 'text', 'body' => 'Nested', 'image' => 'medias://'.$ids['media']],
            ]],
        ], $data['blocks']);

        $page = Page::query()->findOrFail($ids['page']);
        $this->assertSame([$ids['media']], $page->medias(MediaUsagesSync::HANDLE)->pluck('id')->all());
        $this->assertSame($ids['parent'], $page->parent_id);
        $blocks = $page->toEntity()->blocks();
        $this->assertSame('intro', $blocks[0]->handle());
        $this->assertSame('Nested', $blocks[1]->blocks()[0]->body());
        $this->assertSame($ids['parent'], (int) $page->toEntity()->parent()?->id());
    }

    public function test_it_has_nothing_left_to_convert_after()
    {
        $this->legacyPage();
        (new Upgrade)->convertBlocks($this->site(), true);

        $this->assertTrue((new Upgrade)->convertBlocks($this->site(), false)->isEmpty());
    }

    public function test_the_command_converts_the_pages()
    {
        $ids = $this->legacyPage();

        $this->artisan('folklore:upgrade', ['--dry-run' => true])
            ->expectsOutputToContain('Would convert the data of 1 pages')
            ->assertSuccessful();
        $this->assertSame('blocks://999', json_decode((string) DB::table('pages')->where('id', $ids['page'])->value('data'), true)['blocks'][2]);

        $this->artisan('folklore:upgrade', ['--force' => true])
            ->expectsOutputToContain('Converted the data of 1 pages: 3 blocks put into their page, 1 parents as `page://`.')
            ->assertSuccessful();
        $this->assertCount(2, json_decode((string) DB::table('pages')->where('id', $ids['page'])->value('data'), true)['blocks']);
    }
}
