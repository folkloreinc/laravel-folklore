<?php

namespace Folklore\Tests\Unit\Support;

use Folklore\JsonData\Schema\Json;
use Folklore\Support\OtherLocales;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('core')]
class OtherLocalesTest extends TestCase
{
    public function test_a_translatable_value_keeps_the_locales_it_doesnt_give()
    {
        $node = Json::string()->translatable();

        $this->assertSame(['fr' => 'Nouveau', 'en' => 'Old'], OtherLocales::keep($node, ['fr' => 'Nouveau'], ['fr' => 'Ancien', 'en' => 'Old']));
        $this->assertSame(['fr' => null, 'en' => 'Old'], OtherLocales::keep($node, ['fr' => null], ['fr' => 'Ancien', 'en' => 'Old']));
    }

    public function test_other_values_are_given_as_they_are()
    {
        $this->assertSame('New', OtherLocales::keep(Json::string(), 'New', 'Old'));
        $this->assertSame(['a'], OtherLocales::keep(Json::list(Json::string()), ['a'], ['b', 'c']));
        $this->assertSame(['fr' => 'A'], OtherLocales::keep(Json::string(), ['fr' => 'A'], ['fr' => 'B', 'en' => 'C']));
        $this->assertSame(['fr' => 'A'], OtherLocales::keep(Json::string()->translatable(), ['fr' => 'A'], null));
    }

    public function test_the_items_of_a_list_are_matched_by_their_id_at_every_level()
    {
        $node = Json::object([
            'blocks' => Json::list(Json::union('type', [
                'text' => Json::object(['body' => Json::string()->translatable()]),
                'stack' => Json::object(['title' => Json::string()->translatable(), 'blocks' => Json::list(Json::object(['body' => Json::string()->translatable()]))]),
            ])),
        ]);
        $stored = ['blocks' => [
            ['id' => 'a', 'type' => 'text', 'body' => ['fr' => 'A', 'en' => 'A en']],
            ['id' => 'b', 'type' => 'stack', 'title' => ['fr' => 'B', 'en' => 'B en'], 'blocks' => [['id' => 'c', 'body' => ['fr' => 'C', 'en' => 'C en']]]],
        ]];

        $kept = OtherLocales::keep($node, ['blocks' => [
            ['id' => 'b', 'type' => 'stack', 'title' => ['fr' => 'B2'], 'blocks' => [['id' => 'c', 'body' => ['fr' => 'C2']], ['body' => ['fr' => 'New']]]],
            ['id' => 'a', 'type' => 'text', 'body' => ['fr' => 'A2']],
            ['id' => 'x', 'type' => 'text', 'body' => ['fr' => 'X']],
        ]], $stored);

        $this->assertSame(['fr' => 'B2', 'en' => 'B en'], $kept['blocks'][0]['title']);
        $this->assertSame(['fr' => 'C2', 'en' => 'C en'], $kept['blocks'][0]['blocks'][0]['body']);
        $this->assertSame(['fr' => 'New'], $kept['blocks'][0]['blocks'][1]['body']);
        $this->assertSame(['fr' => 'A2', 'en' => 'A en'], $kept['blocks'][1]['body']);
        $this->assertSame(['fr' => 'X'], $kept['blocks'][2]['body']);
    }

    public function test_a_block_whose_type_changed_keeps_nothing()
    {
        $node = Json::list(Json::union('type', [
            'text' => Json::object(['body' => Json::string()->translatable()]),
            'quote' => Json::object(['body' => Json::string()->translatable()]),
        ]));

        $kept = OtherLocales::keep($node, [['id' => 'a', 'type' => 'quote', 'body' => ['fr' => 'Q']]], [['id' => 'a', 'type' => 'text', 'body' => ['fr' => 'T', 'en' => 'T en']]]);

        $this->assertSame(['fr' => 'Q'], $kept[0]['body']);
    }

    public function test_the_values_of_a_map_are_matched_by_their_key()
    {
        $node = Json::map(Json::string()->translatable());

        $this->assertSame(
            ['a' => ['fr' => 'A2', 'en' => 'A en'], 'b' => ['fr' => 'B']],
            OtherLocales::keep($node, ['a' => ['fr' => 'A2'], 'b' => ['fr' => 'B']], ['a' => ['fr' => 'A', 'en' => 'A en']]),
        );
    }
}
