<?php

namespace Folklore\Tests\Unit\Support;

use Folklore\Support\Data;
use PHPUnit\Framework\TestCase;

class DataTest extends TestCase
{
    protected array $data = [
        'title' => ['fr' => 'Bonjour', 'en' => 'Hello'],
        'images' => ['medias://1', 'medias://2'],
        'blocks' => [['image' => 'medias://3'], ['image' => null]],
    ];

    public function test_single_wildcard_matches_one_segment()
    {
        $pattern = Data::getPathPattern('blocks.*');

        $this->assertMatchesRegularExpression($pattern, 'blocks.0');
        $this->assertDoesNotMatchRegularExpression($pattern, 'blocks.0.image');
        $this->assertDoesNotMatchRegularExpression($pattern, 'blocks');
    }

    public function test_double_wildcard_matches_any_depth()
    {
        $pattern = Data::getPathPattern('**.image');

        $this->assertMatchesRegularExpression($pattern, 'blocks.0.image');
        $this->assertMatchesRegularExpression($pattern, 'a.b.c.image');
        $this->assertDoesNotMatchRegularExpression($pattern, 'image');
    }

    public function test_path_pattern_escapes_special_characters()
    {
        $pattern = Data::getPathPattern('meta.og:image');

        $this->assertMatchesRegularExpression($pattern, 'meta.og:image');
        $this->assertDoesNotMatchRegularExpression($pattern, 'metaXog:image');
    }

    public function test_dot_flattens_nested_arrays_and_keeps_parent_keys()
    {
        $this->assertSame(
            [
                'title' => null,
                'title.fr' => 'Bonjour',
                'title.en' => 'Hello',
                'images' => null,
                'images.0' => 'medias://1',
                'images.1' => 'medias://2',
                'blocks' => null,
                'blocks.0' => null,
                'blocks.0.image' => 'medias://3',
                'blocks.1' => null,
                'blocks.1.image' => null,
            ],
            Data::dot($this->data),
        );
    }

    public function test_dot_returns_an_empty_array_for_null()
    {
        $this->assertSame([], Data::dot(null));
    }

    public function test_matching_paths()
    {
        $this->assertSame(
            ['images.0', 'images.1'],
            Data::matchingPaths(['images.*'], $this->data)->values()->all(),
        );
        $this->assertSame(
            ['blocks.0.image', 'blocks.1.image'],
            Data::matchingPaths(['blocks.*.image'], $this->data)->values()->all(),
        );
        $this->assertSame(
            ['blocks.0.image', 'blocks.1.image'],
            Data::matchingPaths('**.image', $this->data)->values()->all(),
        );
        $this->assertSame(['title'], Data::matchingPaths(['title'], $this->data)->values()->all());
        $this->assertSame([], Data::matchingPaths(['missing.*'], $this->data)->values()->all());
    }

    public function test_set_paths_with_a_closure()
    {
        $data = Data::setPaths($this->data, ['images.*'], fn ($value) => strtoupper($value));

        $this->assertSame(['MEDIAS://1', 'MEDIAS://2'], $data['images']);
        $this->assertSame($this->data['blocks'], $data['blocks']);
    }

    public function test_set_paths_with_a_value()
    {
        $data = Data::setPaths($this->data, ['blocks.*.image'], 'medias://9');

        $this->assertSame([['image' => 'medias://9'], ['image' => 'medias://9']], $data['blocks']);
    }

    public function test_reduce_paths_passes_the_path_and_its_current_value()
    {
        $visited = [];
        Data::reducePaths(['images.*'], $this->data, function ($data, $path, $value) use (
            &$visited
        ) {
            $visited[$path] = $value;

            return $data;
        });

        $this->assertSame(['images.0' => 'medias://1', 'images.1' => 'medias://2'], $visited);
    }
}
