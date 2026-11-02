<?php

namespace Folklore\Tests\Unit\Upgrade;

use Folklore\Upgrade\ModelFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The model file that `folklore:upgrade --schemas` edits: which returns are literal, and
 * edits that leave the rest of the file as it is.
 */
#[Group('core')]
class ModelFileTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function bodies(): array
    {
        return [
            'short array' => ["return ['image' => 'medias'];", true],
            'long array' => ["return array('image' => 'medias');", true],
            'options and comments' => ["// The images\n        return [\n            'image' => ['relation' => 'medias', 'lazy' => true], // lazy\n        ];", true],
            'a class constant' => ["return [Image::PATH => 'medias'];", true],
            'a closure option' => ["return ['image' => ['relation' => 'medias', 'skip' => fn (\$item) => \$item === null]];", true],
            'array_merge' => ["return array_merge(['image' => 'medias'], []);", false],
            'a call inside' => ["return ['image' => config('relations.image')];", false],
            'a method of the model' => ["return [...\$this->blockRelations(), 'image' => 'medias'];", false],
            'the attribute' => ["return ['image' => \$key === 'data' ? 'medias' : null];", false],
            'two statements' => ["\$relations = ['image' => 'medias'];\n        return \$relations;", false],
            'a match' => ["return match (\$key) { 'data' => ['image' => 'medias'], default => [] };", false],
            'an array then more' => ["return ['image' => 'medias'] + parent::getJsonDataRelations(\$key, \$value);", false],
        ];
    }

    #[DataProvider('bodies')]
    public function test_a_return_is_literal_when_it_is_only_an_array(string $body, bool $literal)
    {
        $file = new ModelFile($this->model("    public function getJsonDataRelations(\$key, \$value, \$attributes = [])\n    {\n        {$body}\n    }"));

        $this->assertSame($literal, $file->method('getJsonDataRelations')['literal'] ?? null);
    }

    public function test_a_method_starts_at_its_doc_comment_and_attributes()
    {
        $code = $this->model("    /**\n     * The references.\n     */\n    #[\\Override]\n    public function getJsonDataRelations(\$key, \$value, \$attributes = [])\n    {\n        return [];\n    }");
        $file = new ModelFile($code);

        $method = $file->method('getJsonDataRelations');

        $this->assertNotNull($method);
        $this->assertSame('    ', $method['indent']);
        $this->assertStringStartsWith("/**\n     * The references.", substr($code, $method['start']));
        $this->assertStringEndsWith("return [];\n    }", substr($code, 0, $method['end']));
        $this->assertNull($file->method('getJsonDataColumnExtract'));
    }

    public function test_it_adds_an_interface_to_a_class_without_one()
    {
        $file = new ModelFile($this->model('', 'class Game extends Model'));

        $file->replaceInterfaces(['Folklore\\Contracts\\Eloquent\\HasJsonDataRelations'], 'HasJsonDataSchema', 'Folklore\\JsonData\\Contracts\\HasJsonDataSchema');

        $this->assertStringContainsString("class Game extends Model implements HasJsonDataSchema\n{", $file->code());
    }

    public function test_it_replaces_interfaces_written_fully_qualified()
    {
        $file = new ModelFile($this->model('', "class Game extends Model implements \\Folklore\\Contracts\\Eloquent\\HasJsonDataRelations,\n    ToEntity"));

        $file->replaceInterfaces(['Folklore\\Contracts\\Eloquent\\HasJsonDataRelations'], 'HasJsonDataSchema', 'Folklore\\JsonData\\Contracts\\HasJsonDataSchema');

        $this->assertStringContainsString('class Game extends Model implements ToEntity, HasJsonDataSchema', $file->code());
    }

    public function test_imports_go_in_order_when_the_imports_are_sorted()
    {
        $file = new ModelFile($this->model(''));

        $this->assertSame('Json', $file->import('Folklore\\JsonData\\Schema\\Json'));
        $this->assertSame('Json', $file->import('Folklore\\JsonData\\Schema\\Json'));

        $this->assertStringContainsString("use Folklore\\Contracts\\Eloquent\\HasJsonDataRelations;\nuse Folklore\\JsonData\\Schema\\Json;\nuse Illuminate\\Database\\Eloquent\\Model;", $file->code());
    }

    public function test_imports_go_last_when_the_imports_are_not_sorted()
    {
        $file = new ModelFile("<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\nuse App\\Contracts\\Game as GameContract;\n\nclass Game extends Model\n{\n}\n");

        $file->import('Folklore\\JsonData\\Schema\\Json');

        $this->assertStringContainsString("use App\\Contracts\\Game as GameContract;\nuse Folklore\\JsonData\\Schema\\Json;\n\nclass", $file->code());
    }

    public function test_a_short_name_that_is_taken_is_written_fully_qualified()
    {
        $file = new ModelFile("<?php\n\nnamespace App\\Models;\n\nuse App\\Support\\Json;\n\nclass Game\n{\n}\n");

        $this->assertSame('\\Folklore\\JsonData\\Schema\\Json', $file->import('Folklore\\JsonData\\Schema\\Json'));
        $this->assertStringNotContainsString('use Folklore', $file->code());
    }

    public function test_an_import_still_used_is_kept()
    {
        $file = new ModelFile($this->model("    public function relations(): HasJsonDataRelations\n    {\n        return \$this;\n    }"));

        $file->removeUnusedImport('Folklore\\Contracts\\Eloquent\\HasJsonDataRelations');

        $this->assertStringContainsString('use Folklore\\Contracts\\Eloquent\\HasJsonDataRelations;', $file->code());
    }

    public function test_removing_a_method_leaves_one_blank_line()
    {
        $file = new ModelFile($this->model("    public function a()\n    {\n    }\n\n    public function b()\n    {\n    }\n\n    public function c()\n    {\n    }"));

        $file->removeMethod('b');

        $this->assertStringContainsString("    public function a()\n    {\n    }\n\n    public function c()", $file->code());
    }

    private function model(string $body, string $declaration = 'class Game extends Model implements HasJsonDataRelations'): string
    {
        return "<?php\n\nnamespace App\\Models;\n\nuse Folklore\\Contracts\\Eloquent\\HasJsonDataRelations;\nuse Illuminate\\Database\\Eloquent\\Model;\n\n{$declaration}\n{\n{$body}\n}\n";
    }
}
