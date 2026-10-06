<?php

namespace Folklore\Tests\Unit\Support;

use Folklore\Support\ShortUuid;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class ShortUuidTest extends TestCase
{
    public function test_encodes_a_known_uuid()
    {
        $shortUuid = new ShortUuid;

        $this->assertSame(
            'fpfyRTmt6XeE9ehEKZ5LwF',
            $shortUuid->encode(Uuid::fromString('4e52c919-513e-4562-9248-7dd612c6c1ca')),
        );
    }

    public function test_decodes_a_known_short_uuid()
    {
        $shortUuid = new ShortUuid;

        $this->assertSame(
            '4e52c919-513e-4562-9248-7dd612c6c1ca',
            $shortUuid->decode('fpfyRTmt6XeE9ehEKZ5LwF')->toString(),
        );
    }

    public function test_round_trip()
    {
        $shortUuid = new ShortUuid;
        $uuid = Uuid::uuid4();

        $this->assertTrue($uuid->equals($shortUuid->decode($shortUuid->encode($uuid))));
    }

    public function test_generates_short_uuids()
    {
        $this->assertMatchesRegularExpression('/^[2-9A-HJ-NP-Za-km-z]{21,22}$/', ShortUuid::uuid4());
        $this->assertSame(
            ShortUuid::uuid5(Uuid::NAMESPACE_URL, 'https://folklore.email'),
            ShortUuid::uuid5(Uuid::NAMESPACE_URL, 'https://folklore.email'),
        );
    }

    public function test_custom_alphabet()
    {
        $shortUuid = new ShortUuid(['0', '1']);
        $uuid = Uuid::uuid4();

        $this->assertSame(['0', '1'], $shortUuid->getAlphabet());
        $this->assertMatchesRegularExpression('/^[01]+$/', $shortUuid->encode($uuid));
        $this->assertTrue($uuid->equals($shortUuid->decode($shortUuid->encode($uuid))));
    }
}
