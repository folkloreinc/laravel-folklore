<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Contracts\Entities\Image;
use Folklore\Contracts\Repositories\Pages;
use Folklore\Entities\Blocks\ButtonBlock;
use Folklore\Entities\Blocks\EmbedBlock;
use Folklore\Entities\Blocks\GalleryBlock;
use Folklore\Entities\Blocks\HeadingBlock;
use Folklore\Entities\Blocks\ImageBlock;
use Folklore\Entities\Blocks\QuoteBlock;
use Folklore\Entities\Blocks\TextBlock;
use Folklore\Pages\BlockRegistry;
use Illuminate\Support\Facades\DB;

/**
 * The standard block types (docs/decisions/0025): their schema, their entities and their
 * fields in the pages resource.
 */
class StandardBlocksTest extends StandardResourcesTestCase
{
    private const PATH = '/panneau/api/resources/pages';

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function pageWithBlocks(array $blocks): string
    {
        return $this->app->make(Pages::class)->create(['title' => ['fr' => 'Page'], 'blocks' => $blocks])->id();
    }

    public function test_each_type_reads_its_data()
    {
        $id = $this->pageWithBlocks([
            ['type' => 'text', 'title' => ['fr' => 'Titre', 'en' => 'Title'], 'body' => ['fr' => '<p>Texte</p>']],
            ['type' => 'heading', 'title' => ['fr' => 'Intertitre']],
            ['type' => 'quote', 'body' => ['fr' => 'Citation'], 'author' => 'Auteur'],
            ['type' => 'button', 'label' => ['fr' => 'Lire'], 'url' => ['fr' => 'https://example.com'], 'external' => true],
            ['type' => 'embed', 'embed' => ['url' => 'https://www.youtube.com/watch?v=1', 'provider' => 'YouTube', 'title' => 'A video']],
        ]);
        $this->app->setLocale('fr');

        $blocks = $this->app->make(Pages::class)->findById($id)?->blocks();

        $this->assertInstanceOf(TextBlock::class, $blocks[0]);
        $this->assertSame('Titre', $blocks[0]->title());
        $this->assertSame('Title', $blocks[0]->title('en'));
        $this->assertSame('<p>Texte</p>', $blocks[0]->body());
        $this->assertNull($blocks[0]->body('en'));
        $this->assertInstanceOf(HeadingBlock::class, $blocks[1]);
        $this->assertSame('Intertitre', $blocks[1]->title());
        $this->assertInstanceOf(QuoteBlock::class, $blocks[2]);
        $this->assertSame('Citation', $blocks[2]->body());
        $this->assertSame('Auteur', $blocks[2]->author());
        $this->assertInstanceOf(ButtonBlock::class, $blocks[3]);
        $this->assertSame('Lire', $blocks[3]->label());
        $this->assertSame('https://example.com', $blocks[3]->url());
        $this->assertTrue($blocks[3]->external());
        $this->assertInstanceOf(EmbedBlock::class, $blocks[4]);
        $this->assertSame('https://www.youtube.com/watch?v=1', $blocks[4]->url());
        $this->assertSame('YouTube', $blocks[4]->embed()['provider'] ?? null);
    }

    public function test_the_image_and_gallery_read_their_images()
    {
        $first = $this->image('First');
        $second = $this->image('Second');
        $id = $this->pageWithBlocks([
            ['type' => 'image', 'image' => ['id' => $first->uid], 'caption' => ['fr' => 'Légende'], 'credits' => ['fr' => 'Photo']],
            ['type' => 'gallery', 'images' => [['id' => $second->uid], ['id' => $first->uid]]],
        ]);

        $blocks = $this->app->make(Pages::class)->findById($id)?->blocks();

        $this->assertInstanceOf(ImageBlock::class, $blocks[0]);
        $this->assertInstanceOf(Image::class, $blocks[0]->image());
        $this->assertSame((string) $first->id, $blocks[0]->image()->id());
        $this->assertSame('Légende', $blocks[0]->caption('fr'));
        $this->assertSame('Photo', $blocks[0]->credits('fr'));
        $this->assertInstanceOf(GalleryBlock::class, $blocks[1]);
        $this->assertSame([(string) $second->id, (string) $first->id], $blocks[1]->images()->map->id()->all());
    }

    public function test_the_admin_saves_and_reads_the_images_of_blocks()
    {
        $image = $this->image('Portrait');

        $created = $this->postJson(self::PATH, [
            'title' => ['fr' => 'Page', 'en' => 'Page'],
            'blocks' => [
                ['type' => 'image', 'image' => ['id' => $image->uid], 'caption' => ['fr' => 'Légende']],
                ['type' => 'gallery', 'images' => [['id' => $image->uid]]],
            ],
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.blocks.0.image.id', $image->uid)
            ->assertJsonPath('data.blocks.0.image.resource', 'medias')
            ->assertJsonPath('data.blocks.0.image.label', 'Portrait')
            ->assertJsonPath('data.blocks.0.caption.fr', 'Légende')
            ->assertJsonPath('data.blocks.1.images.0.id', $image->uid);
    }

    public function test_the_admin_saves_only_the_fields_of_a_type()
    {
        $created = $this->postJson(self::PATH, [
            'title' => ['fr' => 'Page', 'en' => 'Page'],
            'blocks' => [['type' => 'quote', 'body' => ['fr' => 'Citation'], 'author' => 'Auteur', 'unknown' => 'value']],
        ])->assertCreated();

        $stored = json_decode((string) DB::table('pages')->where('id', $created->json('data.id'))->value('data'), true)['blocks'][0];
        $this->assertSame(['id', 'type', 'body', 'author'], array_keys($stored));
    }

    public function test_the_schemas_have_the_properties_of_their_fields()
    {
        $registry = $this->app->make(BlockRegistry::class);
        $variants = $registry->union()?->getVariants() ?? [];

        foreach (self::BLOCK_TYPES as $type => $class) {
            $fields = array_map(fn ($field) => $field->name(), $class::blockFields());
            $properties = array_diff(array_keys($variants[$type]->getProperties()), ['id', 'handle']);
            $this->assertSame($fields, array_values($properties), $type);
            $this->assertFalse($variants[$type]->allowsAdditional(), $type);
            $this->assertNotSame('', $class::blockLabel());
            $this->assertNotNull($class::blockIcon());
        }
    }

    public function test_the_stored_blocks_have_only_their_properties()
    {
        $id = $this->pageWithBlocks([['type' => 'heading', 'title' => ['fr' => 'Intertitre']]]);

        $stored = json_decode((string) DB::table('pages')->where('id', $id)->value('data'), true)['blocks'][0];

        $this->assertSame(['id', 'type', 'title'], array_keys($stored));
    }
}
