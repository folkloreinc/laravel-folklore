<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Contracts\Repositories\Pages;
use Folklore\Panneau\Fields\Blocks;
use Folklore\Tests\Feature\Panneau\Fixtures\Site\PagesWithSubtitle;
use Illuminate\Support\Facades\DB;
use Panneau\Layout\Node;

/**
 * The standard pages resource (docs/decisions/0025), extended by an empty class of the site.
 */
class PagesResourceTest extends StandardResourcesTestCase
{
    private const PATH = '/panneau/api/resources/pages';

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(array $data): string
    {
        return $this->app->make(Pages::class)->create($data)->id();
    }

    /**
     * The data of a page as stored, without the cast.
     *
     * @return array<string, mixed>
     */
    private function storedData(string $id): array
    {
        return json_decode((string) DB::table('pages')->where('id', $id)->value('data'), true);
    }

    public function test_its_definition_has_the_fields_of_a_page_and_the_publication_at_the_bottom()
    {
        $definition = $this->resourceDefinition('pages');

        $this->assertSame('file-text', $definition['icon']);
        $this->assertSame('edit', $definition['open']);
        $this->assertSame(
            ['title' => 'text', 'slug' => 'slug', 'description' => 'textarea', 'parent' => 'relation', 'blocks' => 'repeater', 'published' => 'boolean'],
            array_column($definition['fields'], 'type', 'name'),
        );
        $fields = array_column($definition['fields'], null, 'name');
        foreach (['title', 'slug', 'description'] as $name) {
            $this->assertTrue($fields[$name]['localized'], $name);
        }
        $this->assertSame('pages', $fields['parent']['resource']);
        $this->assertSame(['widget' => 'switch'], $fields['published']['settings']);
        $this->assertSame(
            [
                ['type' => 'field', 'name' => 'title'],
                ['type' => 'field', 'name' => 'slug'],
                ['type' => 'field', 'name' => 'description'],
                ['type' => 'field', 'name' => 'parent'],
                ['type' => 'field', 'name' => 'blocks'],
                ['type' => 'component', 'component' => 'publication', 'children' => [['type' => 'field', 'name' => 'published']]],
            ],
            $definition['form']['layout'],
        );
    }

    public function test_the_title_is_required_the_slug_editable_and_the_description_optional()
    {
        $fields = array_column($this->resourceDefinition('pages')['fields'], null, 'name');

        $this->assertContains(['rule' => 'required'], $fields['title']['rules'] ?? []);
        $this->assertArrayNotHasKey('readOnly', $fields['slug']);
        $this->assertNotContains(['rule' => 'required'], $fields['description']['rules'] ?? []);

        $this->postJson(self::PATH, ['description' => ['fr' => 'Sans titre']])->assertUnprocessable();
        // In each locale of the admin, as Panneau requires a localized field
        $this->postJson(self::PATH, ['title' => ['fr' => 'Titre']])->assertUnprocessable()->assertJsonValidationErrors('title.en');
        $created = $this->postJson(self::PATH, ['title' => ['fr' => 'Titre', 'en' => 'Title'], 'slug' => ['fr' => 'mon-adresse']])->assertCreated();
        $created->assertJsonPath('data.slug.fr', 'mon-adresse')->assertJsonPath('data.description.fr', null);

        $this->patchJson(self::PATH.'/'.$created->json('data.id'), ['slug' => ['fr' => 'autre-adresse']])
            ->assertOk()
            ->assertJsonPath('data.slug.fr', 'autre-adresse');
    }

    public function test_its_blocks_are_the_standard_types_of_the_site()
    {
        $blocks = array_column($this->resourceDefinition('pages')['fields'], null, 'name')['blocks'];

        $this->assertSame(array_keys(self::BLOCK_TYPES), array_column($blocks['types'], 'id'));
        $types = array_column($blocks['types'], null, 'id');
        $this->assertSame('image', $types['image']['icon']);
        $this->assertSame(['image', 'caption', 'credits'], array_column($types['image']['fields'], 'name'));
        $this->assertSame(['images'], array_column($types['gallery']['fields'], 'name'));
        $this->assertTrue($types['gallery']['fields'][0]['multiple']);
    }

    public function test_its_list_sorts_by_title_and_publication_and_searches()
    {
        $list = $this->resourceDefinition('pages')['list'];

        $this->assertSame(['title', 'published'], array_column($list['columns'], 'field'));
        $this->assertSame(['title', 'published'], $list['sorts']);
        $this->assertTrue($list['search']);
    }

    public function test_its_labels_are_translated()
    {
        $this->app->setLocale('fr');

        $fields = array_column($this->app->make(PagesWithSubtitle::class)->fields(), null);
        $labels = [];
        foreach ($this->app->make(Fixtures\Site\Pages::class)->fields() as $field) {
            $labels[$field->name()] = $field->toDefinition()['label'];
        }

        $this->assertNotEmpty($fields);
        $this->assertSame('Titre', $labels['title']);
        $this->assertSame('Page parente', $labels['parent']);
        $this->assertSame('Publiée', $labels['published']);
        $this->assertSame('Pages', $this->app->make(Fixtures\Site\Pages::class)->pluralLabel());
    }

    public function test_a_page_is_created_with_its_blocks_and_read_back()
    {
        $created = $this->postJson(self::PATH, [
            'title' => ['fr' => 'À propos', 'en' => 'About'],
            'description' => ['fr' => 'Qui nous sommes'],
            'published' => true,
            'blocks' => [
                ['type' => 'heading', 'title' => ['fr' => 'Intertitre']],
                ['type' => 'text', 'title' => ['fr' => 'Titre'], 'body' => ['fr' => '<p>Texte</p>']],
                ['type' => 'quote', 'body' => ['fr' => '<p>Citation</p>'], 'author' => 'Quelqu’un'],
                ['type' => 'button', 'label' => ['fr' => 'Lire'], 'url' => ['fr' => 'https://example.com'], 'external' => true],
            ],
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.title.fr', 'À propos')
            ->assertJsonPath('data.title.en', 'About')
            ->assertJsonPath('data.slug.fr', 'a-propos')
            ->assertJsonPath('data.description.fr', 'Qui nous sommes')
            ->assertJsonPath('data.published', true)
            ->assertJsonPath('data.blocks.0.type', 'heading')
            ->assertJsonPath('data.blocks.1.body.fr', '<p>Texte</p>')
            ->assertJsonPath('data.blocks.2.author', 'Quelqu’un')
            ->assertJsonPath('data.blocks.3.external', true);
        $id = $created->json('data.id');
        $this->getJson(self::PATH.'/'.$id)->assertOk()->assertJsonPath('data.blocks.3.url.fr', 'https://example.com');

        $page = $this->app->make(Pages::class)->findById($id);
        $this->assertSame('Intertitre', $page?->blocks()[0]->title('fr'));
        $this->assertSame('Lire', $page?->blocks()[3]->label('fr'));
    }

    public function test_a_page_has_a_parent()
    {
        $parent = $this->page(['title' => ['fr' => 'Parent']]);

        $created = $this->postJson(self::PATH, ['title' => ['fr' => 'Enfant', 'en' => 'Child'], 'parent' => $parent])->assertCreated();

        $created->assertJsonPath('data.parent.id', $parent)->assertJsonPath('data.parent.label.fr', 'Parent');
        $this->assertSame($parent, $this->app->make(Pages::class)->findById($created->json('data.id'))?->parent()?->id());
    }

    public function test_the_list_sorts_by_the_title_in_the_current_locale()
    {
        foreach ([['Banane', 'Zebra'], ['Ananas', 'Yak'], ['Cerise', 'Xylophone']] as [$fr, $en]) {
            $this->page(['title' => ['fr' => $fr, 'en' => $en]]);
        }

        // The locale of the request, which laravel-locale's middleware sets
        $this->getJson(self::PATH.'?sort=title', ['Accept-Language' => 'fr'])->assertOk()
            ->assertJsonPath('data.*.title.fr', ['Ananas', 'Banane', 'Cerise']);
        $this->getJson(self::PATH.'?sort=-title', ['Accept-Language' => 'fr'])->assertOk()
            ->assertJsonPath('data.*.title.fr', ['Cerise', 'Banane', 'Ananas']);
        $this->getJson(self::PATH.'?sort=title', ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonPath('data.*.title.en', ['Xylophone', 'Yak', 'Zebra']);
    }

    public function test_the_list_sorts_by_publication()
    {
        $this->page(['title' => ['fr' => 'Publiée'], 'published' => true]);
        $this->page(['title' => ['fr' => 'Brouillon'], 'published' => false]);

        $this->getJson(self::PATH.'?sort=published')->assertOk()
            ->assertJsonPath('data.*.title.fr', ['Brouillon', 'Publiée']);
        $this->getJson(self::PATH.'?sort=-published')->assertOk()
            ->assertJsonPath('data.*.title.fr', ['Publiée', 'Brouillon']);
    }

    public function test_the_list_searches_the_titles_in_every_locale()
    {
        $this->page(['title' => ['fr' => 'À propos', 'en' => 'About us']]);
        $this->page(['title' => ['fr' => 'Contact', 'en' => 'Contact']]);

        $this->getJson(self::PATH.'?q=PROPOS')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::PATH.'?q=about')->assertOk()->assertJsonPath('data.0.title.fr', 'À propos');
        $this->getJson(self::PATH.'?q=nothing')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_saving_keeps_the_locales_that_the_admin_doesnt_edit()
    {
        $id = $this->page([
            'title' => ['fr' => 'À propos', 'en' => 'About'],
            'description' => ['fr' => 'Nous', 'en' => 'Us'],
            'blocks' => [
                ['type' => 'text', 'title' => ['fr' => 'Titre', 'en' => 'Title'], 'body' => ['fr' => 'Texte', 'en' => 'Text']],
                ['type' => 'button', 'label' => ['fr' => 'Lire', 'en' => 'Read'], 'url' => ['fr' => 'https://example.com/fr', 'en' => 'https://example.com/en']],
            ],
        ]);
        // The admin edits the French only
        config(['panneau.locales' => ['fr']]);
        $blocks = $this->getJson(self::PATH.'/'.$id)->assertOk()->json('data.blocks');
        // A client that sends the locales of the admin only
        $blocks = [
            ['id' => $blocks[0]['id'], 'type' => 'text', 'title' => ['fr' => 'Titre'], 'body' => ['fr' => 'Texte modifié']],
            ['id' => $blocks[1]['id'], 'type' => 'button', 'label' => ['fr' => 'Lire la suite'], 'url' => ['fr' => 'https://example.com/fr']],
        ];

        $this->patchJson(self::PATH.'/'.$id, [
            'title' => ['fr' => 'À propos de nous'],
            'description' => ['fr' => null],
            'blocks' => $blocks,
        ])->assertOk();

        $data = $this->storedData($id);
        $this->assertSame(['fr' => 'À propos de nous', 'en' => 'About'], $data['title']);
        $this->assertSame('Us', $data['description']['en']);
        $this->assertNull($data['description']['fr'] ?? null);
        $this->assertSame(['fr' => 'Texte modifié', 'en' => 'Text'], $data['blocks'][0]['body']);
        $this->assertSame(['fr' => 'Titre', 'en' => 'Title'], $data['blocks'][0]['title']);
        $this->assertSame(['fr' => 'Lire la suite', 'en' => 'Read'], $data['blocks'][1]['label']);
        $this->assertSame('https://example.com/en', $data['blocks'][1]['url']['en']);
    }

    public function test_a_new_block_keeps_only_what_it_is_given()
    {
        $id = $this->page(['title' => ['fr' => 'Page', 'en' => 'Page'], 'blocks' => [['type' => 'text', 'body' => ['fr' => 'A', 'en' => 'B']]]]);
        config(['panneau.locales' => ['fr']]);

        $this->patchJson(self::PATH.'/'.$id, ['blocks' => [['type' => 'text', 'body' => ['fr' => 'Nouveau']]]])->assertOk();

        $this->assertSame(['fr' => 'Nouveau'], $this->storedData($id)['blocks'][0]['body']);
    }

    public function test_a_site_adds_and_removes_fields_without_copying_them()
    {
        $resource = $this->app->make(PagesWithSubtitle::class);

        $this->assertSame(['title', 'subtitle', 'slug', 'parent', 'blocks', 'published'], array_map(fn ($field) => $field->name(), $resource->fields()));
        $layout = array_map(fn (Node $node): array => $node->toDefinition(), $resource->layout());
        $this->assertSame(['title', 'subtitle', 'slug', 'parent', 'blocks'], array_column(array_slice($layout, 0, 5), 'name'));
        $this->assertSame('publication', $layout[5]['component']);
        $blocks = $resource->field('blocks');
        $this->assertInstanceOf(Blocks::class, $blocks);
        $this->assertNotContains('gallery', array_map(fn ($type) => $type->id(), $blocks->blockTypes()));
        $this->assertContains('image', array_map(fn ($type) => $type->id(), $blocks->blockTypes()));
    }
}
