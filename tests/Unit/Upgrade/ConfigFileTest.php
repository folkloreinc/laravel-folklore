<?php

namespace Folklore\Tests\Unit\Upgrade;

use Folklore\Upgrade\ConfigFile;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('core')]
class ConfigFileTest extends TestCase
{
    public function test_it_replaces_a_value_and_keeps_the_rest_of_the_file()
    {
        $file = new ConfigFile(<<<'PHP'
        <?php

        return [
            'modules' => [
                // The medias module
                'medias' => false,

                'pages' => false,
            ],
        ];
        PHP);

        $file->set('modules.medias', true);

        $this->assertSame(<<<'PHP'
        <?php

        return [
            'modules' => [
                // The medias module
                'medias' => true,

                'pages' => false,
            ],
        ];
        PHP, $file->code());
    }

    public function test_it_adds_a_key_after_the_last_one()
    {
        $file = new ConfigFile(<<<'PHP'
        <?php

        return [
            'name' => env('SITE_NAME'),
            'modules' => [
                'medias' => true
            ],
        ];
        PHP);

        $file->set('modules.auth', true);
        $file->set('medias.reference_key', 'medias');

        $this->assertSame(<<<'PHP'
        <?php

        return [
            'name' => env('SITE_NAME'),
            'modules' => [
                'medias' => true,
                'auth' => true,
            ],
            'medias' => [
                'reference_key' => 'medias',
            ],
        ];
        PHP, $file->code());
        $this->assertTrue($file->get('modules.auth'));
        $this->assertSame('medias', $file->get('medias.reference_key'));
    }

    public function test_it_fills_an_empty_array_and_keeps_an_inline_one_on_its_line()
    {
        $file = new ConfigFile(<<<'PHP'
        <?php

        return [
            'modules' => [],
            'medias' => array('resource' => 'canonical'),
        ];
        PHP);

        $file->set('modules.medias', true);
        $file->set('medias.reference_key', "it's");

        $this->assertSame(<<<'PHP'
        <?php

        return [
            'modules' => [
                'medias' => true,
            ],
            'medias' => array('resource' => 'canonical', 'reference_key' => 'it\'s'),
        ];
        PHP, $file->code());
        $this->assertSame("it's", $file->get('medias.reference_key'));
        $this->assertSame('canonical', $file->get('medias.resource'));
    }

    public function test_it_reads_only_literals()
    {
        $file = new ConfigFile("<?php\n\nreturn ['name' => env('SITE_NAME'), 'count' => 3, 'on' => false, 'list' => ['a', 'b']];\n");

        $this->assertNull($file->get('name'));
        $this->assertSame(3, $file->get('count'));
        $this->assertFalse($file->get('on'));
        $this->assertNull($file->get('list.a'));
        $this->assertNull($file->get('missing'));
    }

    public function test_it_refuses_to_write_under_a_value_that_is_not_an_array_literal()
    {
        $file = new ConfigFile("<?php\n\nreturn ['modules' => \$modules];\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('`modules` is not an array literal');

        $file->set('modules.medias', true);
    }

    public function test_it_refuses_a_file_that_does_not_parse()
    {
        $this->expectException(RuntimeException::class);

        (new ConfigFile("<?php\n\nreturn ['modules' => [;\n"))->set('modules.medias', true);
    }

    public function test_it_refuses_a_file_that_does_not_return_an_array()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not return an array literal');

        (new ConfigFile("<?php\n\nreturn require __DIR__.'/defaults.php';\n"))->set('modules.medias', true);
    }
}
