<?php

namespace Folklore\Tests\Unit\Support;

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Entities\ToEntity;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function test_to_id_returns_scalars_as_is()
    {
        $this->assertSame(5, to_id(5));
        $this->assertSame('abc', to_id('abc'));
    }

    public function test_to_id_reads_the_id_key_of_an_array()
    {
        $this->assertSame(7, to_id(['id' => 7]));
        $this->assertNull(to_id(['name' => 'no id']));
    }

    public function test_to_id_reads_the_key_of_a_model()
    {
        $model = new class extends Model {};
        $model->forceFill(['id' => 42]);

        $this->assertSame(42, to_id($model));
    }

    public function test_to_id_reads_the_id_of_an_entity()
    {
        $this->assertSame('12', to_id($this->makeEntity('12')));
    }

    public function test_to_id_converts_to_entity_before_reading_the_id()
    {
        $this->assertSame('34', to_id($this->makeToEntity($this->makeEntity('34'))));
    }

    public function test_to_id_returns_null_for_unsupported_values()
    {
        $this->assertNull(to_id(null));
        $this->assertNull(to_id(new \stdClass));
    }

    public function test_to_entity_converts_only_to_entity_instances()
    {
        $entity = $this->makeEntity('1');

        $this->assertSame($entity, to_entity($this->makeToEntity($entity)));
        $this->assertSame($entity, to_entity($entity));
        $this->assertNull(to_entity(null));
        $this->assertSame('value', to_entity('value'));
    }

    protected function makeEntity(string $id): Entity
    {
        return new class($id) implements Entity
        {
            public function __construct(protected string $id) {}

            public function id(): string
            {
                return $this->id;
            }
        };
    }

    protected function makeToEntity(Entity $entity): ToEntity
    {
        return new class($entity) implements ToEntity
        {
            public function __construct(protected Entity $entity) {}

            public function toEntity(): ?Entity
            {
                return $this->entity;
            }
        };
    }
}
