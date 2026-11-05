<?php

namespace Folklore\Repositories\Query;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

/**
 * A value in a JSON column, as text, for a sort or a search: `data->title->fr`. On
 * PostgreSQL, the column is read as `jsonb`, so that a column of text works too, as the
 * `data` of the pages table is.
 */
final class JsonPath implements Expression
{
    /**
     * @param  string  $column  The column, qualified or not.
     * @param  list<string>  $path  The keys within its value.
     */
    public function __construct(
        public readonly string $column,
        public readonly array $path,
        public readonly bool $lowercase = false,
    ) {}

    /**
     * The value in lowercase, to compare it without case: a value read from JSON compares
     * with case on MySQL.
     */
    public function lowercase(): self
    {
        return new self($this->column, $this->path, true);
    }

    /**
     * @return string
     */
    public function getValue(Grammar $grammar)
    {
        if ($grammar instanceof PostgresGrammar) {
            $keys = array_map(fn (string $key): string => $grammar->quoteString($key), $this->path);
            $last = array_pop($keys);
            $value = '(cast('.$grammar->wrap($this->column).' as jsonb)'
                .implode('', array_map(fn (string $key): string => '->'.$key, $keys))
                .'->>'.$last.')';
        } else {
            $value = $grammar->wrap(implode('->', [$this->column, ...$this->path]));
        }

        return $this->lowercase ? 'lower('.$value.')' : $value;
    }
}
