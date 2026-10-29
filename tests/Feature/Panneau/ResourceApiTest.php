<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Tests\Feature\Panneau\Fixtures\ConformanceSeeder;
use Folklore\Tests\Feature\Panneau\Fixtures\ConformanceServiceProvider;
use Folklore\Tests\Feature\Repositories\Fixtures\Article;

/**
 * Panneau's API on a resource whose repository is a Folklore repository.
 */
class ResourceApiTest extends PanneauTestCase
{
    private const PATH = '/panneau/api/resources/articles';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ConformanceSeeder::class);
        $this->withToken(ConformanceServiceProvider::TOKEN);
    }

    private function uidOf(string $title): string
    {
        $uid = Article::query()->where('title', $title)->value('uid');
        $this->assertIsString($uid);

        return $uid;
    }

    public function test_the_list_has_the_entities_of_the_scope_by_their_public_id()
    {
        $response = $this->getJson(self::PATH.'?sort=title&perPage=2');

        $response->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.perPage', 2)
            ->assertJsonPath('data.0.id', $this->uidOf('First article'))
            ->assertJsonPath('data.0.title', 'First article')
            ->assertJsonPath('data.0.slug.fr', 'article-1')
            ->assertJsonPath('data.1.title', 'Second article');
    }

    public function test_the_list_is_searched_and_filtered_within_the_scope()
    {
        $this->getJson(self::PATH.'?q=second')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Second article');
        $this->getJson(self::PATH.'?q=another')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(self::PATH.'?filter[workspace]=urbania')->assertOk()->assertJsonPath('meta.total', 3);
        $this->getJson(self::PATH.'?filter[published_at][from]=9999-12-31')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_an_item_has_its_version_as_etag()
    {
        $response = $this->getJson(self::PATH.'/'.$this->uidOf('First article'));

        $response->assertOk()->assertJsonPath('data.title', 'First article');
        $this->assertSame('"'.$response->json('data.version').'"', $response->headers->get('ETag'));
    }

    public function test_an_item_outside_the_scope_is_not_found()
    {
        $this->getJson(self::PATH.'/'.$this->uidOf('Another workspace'))->assertNotFound();
        $this->getJson(self::PATH.'/'.Article::query()->where('title', 'First article')->value('id'))->assertNotFound();
    }

    public function test_an_item_is_created_updated_and_deleted_through_the_repository()
    {
        $created = $this->postJson(self::PATH, [
            'title' => 'Created',
            'slug' => ['fr' => 'cree', 'en' => 'created'],
            'status' => 'draft',
        ]);
        $created->assertCreated()->assertJsonPath('data.slug.fr', 'cree');
        $id = $created->json('data.id');
        $this->assertSame($this->uidOf('Created'), $id);
        $this->assertSame('folklore', Article::query()->where('uid', $id)->value('workspace'));

        $updated = $this->patchJson(self::PATH.'/'.$id, ['title' => 'Updated'], ['If-Match' => '"'.$created->json('data.version').'"']);
        $updated->assertOk()->assertJsonPath('data.title', 'Updated');
        $this->assertNotSame($created->json('data.version'), $updated->json('data.version'));

        $this->patchJson(self::PATH.'/'.$id, ['title' => 'Stale'], ['If-Match' => '"'.$created->json('data.version').'"'])
            ->assertStatus(412);
        $this->patchJson(self::PATH.'/'.$id, ['title' => 'Again'], ['If-Match' => '"'.$updated->json('data.version').'"'])
            ->assertOk();

        $this->deleteJson(self::PATH.'/'.$id)->assertNoContent();
        $this->getJson(self::PATH.'/'.$id)->assertNotFound();
    }

    public function test_the_slug_check_looks_up_the_repository()
    {
        $this->postJson(self::PATH.'/fields/slug/check', ['value' => 'article-1', 'locale' => 'fr', 'id' => null, 'values' => []])
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('suggestion', 'article-1-2');
        $this->postJson(self::PATH.'/fields/slug/check', ['value' => 'article-1', 'locale' => 'fr', 'id' => $this->uidOf('First article'), 'values' => []])
            ->assertOk()
            ->assertJsonPath('available', true);
        // The slug of an article of another workspace is free in this one
        $this->postJson(self::PATH.'/fields/slug/check', ['value' => 'autre', 'locale' => 'fr', 'id' => null, 'values' => []])
            ->assertOk()
            ->assertJsonPath('available', true);
    }

    public function test_saving_refuses_a_slug_that_is_taken()
    {
        $this->postJson(self::PATH, ['title' => 'Copy', 'slug' => ['fr' => 'article-2'], 'status' => 'draft'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug.fr']);
    }
}
