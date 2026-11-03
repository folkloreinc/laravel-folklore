<?php

namespace Folklore\Tests\Feature\Pages;

use Folklore\Eloquent\MediaUsagesSync;
use Folklore\JsonData\Exceptions\InvalidJsonData;
use Folklore\JsonData\JsonData;
use Folklore\Models\Block;
use Folklore\Models\Media;
use Folklore\Models\Page;
use Folklore\Tests\Feature\Eloquent\JsonDataTestCase;
use Folklore\Tests\Feature\Pages\Fixtures\DescribedPage;
use Folklore\Tests\Feature\Pages\Fixtures\LegacyBlock;
use Folklore\Tests\Feature\Pages\Fixtures\LegacyPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The schemas of the pages module's models: the same data and syncs as their 1.x
 * declarations, which a subclass that overrides them keeps, and a subclass that describes
 * its own keys on top of them (#158).
 */
class PageSchemasTest extends JsonDataTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['site.locales' => ['fr', 'en'], 'app.locale' => 'fr']);
    }

    public function test_a_subclass_that_overrides_the_1x_declarations_keeps_them()
    {
        $this->assertNotNull((new Page)->jsonDataSchema('data'));
        $this->assertNotNull((new Block)->jsonDataSchema('data'));
        $this->assertNull((new LegacyPage)->jsonDataSchema('data'));
        $this->assertNull((new LegacyBlock)->jsonDataSchema('data'));
        $this->assertNull((new Page)->jsonDataSchema('other'));
    }

    public function test_a_page_stores_syncs_and_reads_what_its_1x_declarations_did()
    {
        $results = [];
        foreach ([Page::class, LegacyPage::class] as $class) {
            $parent = Page::create(['handle' => 'parent']);
            $image = $this->makeMedia();
            $blocks = [$this->makeBlock(), $this->makeBlock()];

            /** @var Page $page */
            $page = new $class;
            $page->fill(['handle' => 'page', 'data' => [
                'title' => ['fr' => 'Titre'],
                'parent' => $parent,
                'image' => $image,
                'blocks' => $blocks,
            ]]);
            $page->save();

            $fresh = $class::query()->findOrFail($page->id);
            $results[] = [
                'stored' => $this->placeholders($this->rawData($page), ['parent' => $parent, 'image' => $image, 'block0' => $blocks[0], 'block1' => $blocks[1]]),
                'parent_id' => $fresh->parent_id === $parent->id,
                'blocks' => $this->blocksPivot($page) === [$blocks[0]->id, $blocks[1]->id],
                'medias' => $fresh->medias(MediaUsagesSync::HANDLE)->pluck('id')->all() === [$image->id],
                'read' => [
                    $fresh->data['parent'] instanceof Page ? $fresh->data['parent']->id === $parent->id : null,
                    $fresh->data['image'] instanceof Media ? $fresh->data['image']->id === $image->id : null,
                    array_map(fn ($block) => $block instanceof Block ? $block->id : null, $fresh->data['blocks']) === [$blocks[0]->id, $blocks[1]->id],
                ],
            ];
        }

        $this->assertSame(['fr' => 'Titre'], $results[0]['stored']['title']);
        $this->assertSame([true, true, true], $results[0]['read']);
        $this->assertTrue($results[0]['parent_id'] && $results[0]['blocks'] && $results[0]['medias']);
        $this->assertSame($results[1], $results[0]);
    }

    public function test_a_block_stores_syncs_and_reads_what_its_1x_declarations_did()
    {
        $results = [];
        foreach ([Block::class, LegacyBlock::class] as $class) {
            $image = $this->makeMedia();
            $nested = $this->makeBlock();

            /** @var Block $block */
            $block = new $class;
            $block->fill(['type' => 'stack', 'data' => ['body' => 'Texte', 'image' => $image, 'blocks' => [$nested]]]);
            $block->save();

            $fresh = $class::query()->findOrFail($block->id);
            $results[] = [
                'stored' => $this->placeholders($this->rawData($block), ['image' => $image, 'nested' => $nested]),
                'blocks' => $this->blocksPivot($block) === [$nested->id],
                'medias' => $fresh->medias(MediaUsagesSync::HANDLE)->pluck('id')->all() === [$image->id],
                'read' => [
                    $fresh->data['image'] instanceof Media ? $fresh->data['image']->id === $image->id : null,
                    $fresh->data['blocks'][0] instanceof Block ? $fresh->data['blocks'][0]->id === $nested->id : null,
                ],
            ];
        }

        $this->assertSame('Texte', $results[0]['stored']['body']);
        $this->assertSame([true, true], $results[0]['read']);
        $this->assertTrue($results[0]['blocks'] && $results[0]['medias']);
        $this->assertSame($results[1], $results[0]);
    }

    public function test_a_subclass_describes_its_keys_on_top_of_the_schema()
    {
        $image = $this->makeMedia();
        $page = new DescribedPage(['handle' => 'described']);
        JsonData::fill($page, ['title' => 'Titre', 'image' => $image]);
        $page->save();

        // In the order of the schema; the tests' site writes `medias://` (site.medias.reference_key)
        $this->assertSame(['image' => 'medias://'.$image->id, 'title' => ['fr' => 'Titre']], $this->rawData($page));
        $this->assertSame([$image->id], $page->medias(MediaUsagesSync::HANDLE)->pluck('id')->all());

        $this->expectException(InvalidJsonData::class);
        JsonData::fill($page, ['subtitle' => 'Inconnu']);
    }

    /**
     * The stored data, with each reference to one of the models replaced by its name.
     *
     * @param  array<array-key, mixed>|null  $data
     * @param  array<string, Model>  $models
     * @return array<array-key, mixed>|null
     */
    private function placeholders(?array $data, array $models): ?array
    {
        if (is_null($data)) {
            return null;
        }

        array_walk_recursive($data, function (mixed &$value) use ($models): void {
            foreach ($models as $name => $model) {
                $identifiers = array_filter([$model->getKey(), $model->getAttribute('uid')]);
                if (is_string($value) && preg_match('#^([a-z]+)://(.+)$#', $value, $matches) === 1 && in_array($matches[2], array_map('strval', $identifiers), true) && $this->isOf($matches[1], $model)) {
                    $value = $matches[1].'://{'.$name.'}';
                }
            }
        });

        return $data;
    }

    private function isOf(string $key, Model $model): bool
    {
        return match (true) {
            $model instanceof Media => in_array($key, ['media', 'medias'], true),
            $model instanceof Page => $key === 'parent',
            $model instanceof Block => $key === 'blocks',
            default => false,
        };
    }

    /**
     * @return list<int>
     */
    private function blocksPivot(Model $model): array
    {
        return DB::table('blocks_pivot')
            ->where('blockable_id', $model->getKey())
            ->where('blockable_type', $model->getMorphClass())
            ->orderBy('block_id')
            ->pluck('block_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
