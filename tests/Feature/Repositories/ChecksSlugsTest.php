<?php

namespace Folklore\Tests\Feature\Repositories;

use Folklore\Tests\Feature\Repositories\Fixtures\Article;
use Folklore\Tests\Feature\Repositories\Fixtures\Articles;
use PHPUnit\Framework\Attributes\Group;

/**
 * `Repositories\Concerns\ChecksSlugs`: whether a slug is taken, within the query context.
 */
#[Group('core')]
class ChecksSlugsTest extends NotesTestCase
{
    protected function modules(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function article(array $attributes): Article
    {
        return Article::query()->create(array_merge(['workspace' => 'folklore', 'title' => 'An article'], $attributes));
    }

    public function test_a_localized_slug_is_looked_up_in_the_column_of_its_locale()
    {
        $this->article(['slug_fr' => 'bonjour', 'slug_en' => 'hello']);
        $articles = new Articles;

        $this->assertTrue($articles->slugExists('slug', 'bonjour', 'fr'));
        $this->assertTrue($articles->slugExists('slug', 'hello', 'en'));
        $this->assertFalse($articles->slugExists('slug', 'hello', 'fr'));
        $this->assertFalse($articles->slugExists('slug', 'salut', 'fr'));
    }

    public function test_a_slug_without_locale_is_looked_up_in_the_column_of_its_field()
    {
        $this->article(['title' => 'bonjour']);

        $this->assertTrue((new Articles)->slugExists('title', 'bonjour'));
        $this->assertFalse((new Articles)->slugExists('title', 'salut'));
    }

    public function test_the_entity_being_edited_does_not_take_its_own_slug()
    {
        $article = $this->article(['slug_fr' => 'bonjour'])->toEntity();
        $this->article(['slug_fr' => 'salut']);

        $this->assertFalse((new Articles)->slugExists('slug', 'bonjour', 'fr', $article->id()));
        $this->assertTrue((new Articles)->slugExists('slug', 'salut', 'fr', $article->id()));
    }

    public function test_a_deleted_entity_keeps_its_slug()
    {
        $this->article(['slug_fr' => 'bonjour'])->delete();

        $this->assertTrue((new Articles)->slugExists('slug', 'bonjour', 'fr'));
    }

    public function test_the_lookup_is_within_the_query_context()
    {
        $this->article(['slug_fr' => 'bonjour', 'workspace' => 'urbania']);
        $articles = new Articles;

        $this->assertFalse($articles->withGlobalQuery(['workspace' => 'folklore'])->slugExists('slug', 'bonjour', 'fr'));
        $this->assertTrue($articles->withGlobalQuery(['workspace' => 'urbania'])->slugExists('slug', 'bonjour', 'fr'));
        $this->assertTrue($articles->slugExists('slug', 'bonjour', 'fr'));
    }
}
