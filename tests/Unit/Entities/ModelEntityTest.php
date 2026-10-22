<?php

namespace Folklore\Tests\Unit\Entities;

use Folklore\Entities\ModelEntity;
use Folklore\Tests\Unit\Eloquent\Fixtures\ReferencedMedia;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class ModelEntityTest extends TestCase
{
    public function test_the_id_is_the_route_key()
    {
        $model = new class(['id' => 12, 'uid' => 'med_01JAB3']) extends ReferencedMedia
        {
            public function getRouteKeyName()
            {
                return 'uid';
            }
        };

        $this->assertSame('med_01JAB3', $this->entity($model)->id());
        $this->assertSame('12', $this->entity(new ReferencedMedia(['id' => 12]))->id());
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
