<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Entities\Page;
use Folklore\Contracts\Entities\Image;
use Folklore\Contracts\Entities\PageMetadata as PageMetadataContract;
use Folklore\Contracts\Entities\Pageable as PageableContract;
use Illuminate\Database\Eloquent\Model;

class PageMetadata implements PageMetadataContract, HasModel
{
    protected $page;

    protected $model;

    protected $data;

    protected $image = [];

    protected $video = [];

    public function __construct(PageableContract $page, $model)
    {
        $this->page = $page;
        $this->model = $model;
        $this->data = $model->data;
    }

    public function url(string $locale): string
    {
        return $this->page->url($locale, true);
    }

    public function canonical(string $locale): string
    {
        return $this->page->url($locale, true);
    }

    public function title(string $locale): ?string
    {
        return data_get($this->data, 'title.' . $locale);
    }

    public function description(string $locale): ?string
    {
        return data_get($this->data, 'description.' . $locale);
    }

    public function image(string $locale): ?Image
    {
        return once(function () {
            if ($this->page instanceof Page) {
                return $this->page->image();
            }
            if (method_exists($this->page, 'image')) {
                return $this->page->image();
            }
            return null;
        });
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
