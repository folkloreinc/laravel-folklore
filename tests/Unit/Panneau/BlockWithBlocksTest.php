<?php

namespace Folklore\Tests\Unit\Panneau;

use Folklore\Panneau\Resources\BlockWithBlocks;
use JsonSerializable;
use Panneau\Contracts\ResourceType;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class BlockWithBlocksTest extends TestCase
{
    public function test_it_delegates_to_the_wrapped_type()
    {
        $type = $this->createStub(ResourceType::class);
        $type->method('id')->willReturn('text');
        $type->method('name')->willReturn('Text');
        $type->method('jsonSerialize')->willReturn(['id' => 'text']);

        $block = new BlockWithBlocks($type, 2);

        $this->assertSame(2, $block->currentDepth());
        $this->assertSame('text', $block->id());
        $this->assertSame('Text', $block->name());
        $this->assertSame(['id' => 'text'], $block->jsonSerialize());
        $this->assertSame('{"id":"text"}', json_encode($block));
    }

    public function test_json_serialize_matches_the_json_serializable_signature()
    {
        $method = new ReflectionMethod(BlockWithBlocks::class, 'jsonSerialize');
        $interfaceMethod = new ReflectionMethod(JsonSerializable::class, 'jsonSerialize');

        $this->assertSame(
            (string) $interfaceMethod->getTentativeReturnType(),
            (string) $method->getReturnType(),
        );
    }
}
