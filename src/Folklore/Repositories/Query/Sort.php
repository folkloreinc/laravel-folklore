<?php

namespace Folklore\Repositories\Query;

/**
 * One sort of a query: a field and a direction.
 */
final readonly class Sort
{
    public function __construct(
        public string $field,
        public SortDirection $direction = SortDirection::Ascending,
    ) {}

    public static function ascending(string $field): self
    {
        return new self($field, SortDirection::Ascending);
    }

    public static function descending(string $field): self
    {
        return new self($field, SortDirection::Descending);
    }

    /**
     * Parses one sort: `title`, or `-published_at` for descending.
     */
    public static function parse(string $sort): self
    {
        return str_starts_with($sort, '-')
            ? new self(substr($sort, 1), SortDirection::Descending)
            : new self($sort, SortDirection::Ascending);
    }

    /**
     * Parses comma-separated sorts: `-published_at,title`.
     *
     * @return list<self>
     */
    public static function parseList(string $sorts): array
    {
        $parts = array_filter(
            array_map(trim(...), explode(',', $sorts)),
            fn (string $part): bool => $part !== '' && $part !== '-',
        );

        return array_values(array_map(self::parse(...), $parts));
    }

    public function isDescending(): bool
    {
        return $this->direction === SortDirection::Descending;
    }

    public function toString(): string
    {
        return ($this->isDescending() ? '-' : '').$this->field;
    }
}
