<?php

namespace Folklore\Support;

class Csv
{
    /**
     * Characters that make a spreadsheet read a cell as a formula.
     */
    public const FORMULA_CHARACTERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Neutralize a value that a spreadsheet would read as a formula, by
     * prefixing it with a single quote so that it is shown as text. Numbers,
     * including negative ones, are left as they are.
     */
    public static function escapeFormula(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], self::FORMULA_CHARACTERS, true) ? "'".$value : $value;
    }

    /**
     * Neutralize the formulas of a row.
     */
    public static function escapeFormulas(array $row): array
    {
        return array_map(fn ($value) => self::escapeFormula($value), $row);
    }
}
