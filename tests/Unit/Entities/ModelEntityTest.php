<?php

namespace Folklore\Tests\Unit\Entities;

use Folklore\Entities\ModelEntity;
use Folklore\Tests\Unit\Eloquent\Fixtures\ReferencedMedia;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class ModelEntityTest extends TestCase
{
    public function test_the_id_is_the_id_column_by_default()
    {
        $this->assertSame('12', $this->entity(new ReferencedMedia(['id' => 12, 'uid' => 'med_01JAB3']))->id());
    }

    public function test_the_id_column_can_be_another_column()
    {
        $model = new ReferencedMedia(['id' => 12, 'uid' => 'med_01JAB3']);
        $entity = new class($model) extends ModelEntity
        {
            protected string $idColumn = 'uid';
        };

        $this->assertSame('med_01JAB3', $entity->id());
    }

    public function test_the_id_does_not_follow_the_route_key()
    {
        $model = new class(['id' => 12, 'slug_fr' => 'accueil']) extends ReferencedMedia
        {
            public function getRouteKeyName()
            {
                return 'slug_fr';
            }
        };

        $this->assertSame('12', $this->entity($model)->id());
    }

    public function test_an_unsaved_model_has_an_empty_id()
    {
        $this->assertSame('', $this->entity(new ReferencedMedia)->id());
    }

    public function test_the_model_is_given_to_the_edges_that_need_it()
    {
        $model = new ReferencedMedia(['id' => 12]);

        $this->assertSame($model, $this->entity($model)->getModel());
    }

    /**
     * @return ModelEntity<Model>
     */
    private function entity(Model $model): ModelEntity
    {
        return new class($model) extends ModelEntity {};
    }
}
