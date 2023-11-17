<?php

namespace Folklore\Support;

use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;

class OffsetPaginator extends AbstractPaginator
{
    protected $total;

    /**
     * The query string variable used to store the page.
     *
     * @var string
     */
    protected $pageName = 'offset';

    /**
     * Create a new paginator instance.
     *
     * @param  mixed  $items
     * @param  int  $perPage
     * @param  int|null  $currentPage
     * @param  array  $options  (path, query, fragment, pageName)
     * @return void
     */
    public function __construct($items, $total, $currentOffset = null, array $options = [])
    {
        $this->options = $options;

        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->total = $total;
        $this->currentPage = $this->setCurrentOffsset($currentOffset);
        $this->path = $this->path !== '/' ? rtrim($this->path, '/') : $this->path;

        $this->setItems($items);
    }

    /**
     * Get the current page for the request.
     *
     * @param  int  $currentPage
     * @return int
     */
    protected function setCurrentOffsset($currentOffset)
    {
        $currentOffset = $currentOffset ?: static::resolveCurrentPage($this->pageName);

        return $this->isValidOffset($currentOffset) ? (int) $currentOffset : $this->total;
    }

    /**
     * Determine if the given value is a valid page number.
     *
     * @param  int  $page
     * @return bool
     */
    protected function isValidOffset($page)
    {
        return $page >= 0 && filter_var($page, FILTER_VALIDATE_INT) !== false && $page <= $this->total;
    }

    /**
     * Get the total number of items being paginated.
     *
     * @return int
     */
    public function total()
    {
        return $this->total;
    }

    /**
     * Determine if there are more items in the data source.
     *
     * @return bool
     */
    public function hasMore()
    {
        return ($this->currentOffset() + $this->count()) < $this->total();
    }

    /**
     * Set the items for the paginator.
     *
     * @param  mixed  $items
     * @return void
     */
    protected function setItems($items)
    {
        $this->items = $items instanceof Collection ? $items : Collection::make($items);
        $this->perPage = $items->count();
    }

    /**
     * Get the URL for the next page.
     *
     * @return string|null
     */
    public function nextOffsetUrl()
    {
        if ($this->hasMore()) {
            return $this->url($this->nextOffset());
        }
    }

    public function currentOffset()
    {
        return $this->currentPage();
    }

    public function nextOffset()
    {
        return $this->currentOffset() + $this->count();
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'current_offset' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'first_offset_url' => $this->url(0),
            'from' => $this->firstItem(),
            'next_offset_url' => $this->nextOffsetUrl(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'to' => $this->lastItem(),
        ];
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
}
