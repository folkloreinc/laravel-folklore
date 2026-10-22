<?php

namespace Folklore\Repositories\Query;

use InvalidArgumentException;

/**
 * A typed query of a repository: a page of the entities matching filters, a search, ids
 * and exclusions, in an order. Close to Panneau 5's `ResourceQuery`, without its
 * protocol: the Panneau plugin maps one to the other.
 *
 * Filters are the 1.x params (`type`, `published`, the repository's `$queryColumns`…),
 * so the repository's `buildQueryFromParams()` applies to both APIs.
 */
final readonly class EntitiesQuery
{
    /**
     * @param  array<string, mixed>  $filters  Values keyed by param name.
     * @param  list<Sort>  $sorts
     * @param  list<string>|null  $ids  Only these entities, by public id. Null: no restriction.
     * @param  list<string>  $except  Never these entities, by public id.
     */
    public function __construct(
        public array $filters = [],
        public ?string $search = null,
        public array $sorts = [],
        public ?array $ids = null,
        public array $except = [],
        public int $page = 1,
        public int $perPage = 20,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException("The page must be 1 or more, {$page} given.");
        }
        if ($perPage < 1) {
            throw new InvalidArgumentException("The number of entities per page must be 1 or more, {$perPage} given.");
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function withFilters(array $filters): self
    {
        return new self($filters, $this->search, $this->sorts, $this->ids, $this->except, $this->page, $this->perPage);
    }

    public function withFilter(string $name, mixed $value): self
    {
        return $this->withFilters(array_merge($this->filters, [$name => $value]));
    }

    public function withSearch(?string $search): self
    {
        $search = $search === null ? null : trim($search);

        return new self($this->filters, $search === '' ? null : $search, $this->sorts, $this->ids, $this->except, $this->page, $this->perPage);
    }

    /**
     * @param  list<Sort>  $sorts
     */
    public function withSorts(array $sorts): self
    {
        return new self($this->filters, $this->search, $sorts, $this->ids, $this->except, $this->page, $this->perPage);
    }

    /**
     * @param  list<string>|null  $ids
     */
    public function withIds(?array $ids): self
    {
        $ids = $ids === null ? null : array_values(array_unique($ids));

        return new self($this->filters, $this->search, $this->sorts, $ids, $this->except, $this->page, $this->perPage);
    }

    /**
     * @param  list<string>  $except
     */
    public function withExcept(array $except): self
    {
        return new self($this->filters, $this->search, $this->sorts, $this->ids, array_values(array_unique($except)), $this->page, $this->perPage);
    }

    public function withPage(int $page, ?int $perPage = null): self
    {
        return new self($this->filters, $this->search, $this->sorts, $this->ids, $this->except, $page, $perPage ?? $this->perPage);
    }

    /**
     * The value of a filter, or null when the query doesn't use it.
     */
    public function filter(string $name): mixed
    {
        return $this->filters[$name] ?? null;
    }
}
