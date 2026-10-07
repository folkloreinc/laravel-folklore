<?php

namespace Folklore\Tests\Unit\Support;

use Folklore\Support\Csv;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    public function test_it_neutralizes_formulas()
    {
        $this->assertSame("'=HYPERLINK(\"https://attacker.test\")", Csv::escapeFormula('=HYPERLINK("https://attacker.test")'));
        $this->assertSame("'+1 514 555-1234", Csv::escapeFormula('+1 514 555-1234'));
        $this->assertSame("'-2+3", Csv::escapeFormula('-2+3'));
        $this->assertSame("'@SUM(A1:A2)", Csv::escapeFormula('@SUM(A1:A2)'));
        $this->assertSame("'\t=1+1", Csv::escapeFormula("\t=1+1"));
        $this->assertSame("'\r=1+1", Csv::escapeFormula("\r=1+1"));
    }

    public function test_it_keeps_numbers_and_other_values()
    {
        $this->assertSame('-12.5', Csv::escapeFormula('-12.5'));
        $this->assertSame('+3', Csv::escapeFormula('+3'));
        $this->assertSame(-3, Csv::escapeFormula(-3));
        $this->assertSame(1.5, Csv::escapeFormula(1.5));
        $this->assertSame('hello = world', Csv::escapeFormula('hello = world'));
        $this->assertSame('', Csv::escapeFormula(''));
        $this->assertNull(Csv::escapeFormula(null));
        $this->assertTrue(Csv::escapeFormula(true));
    }

    public function test_it_neutralizes_the_formulas_of_a_row()
    {
        $this->assertSame(
            ['name' => "'=1+1", 'amount' => '-4', 'note' => 'ok'],
            Csv::escapeFormulas(['name' => '=1+1', 'amount' => '-4', 'note' => 'ok']),
        );
    }
}
