<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\HasModel;
use Folklore\Contracts\Resources\PageMetadata as PageMetadataContract;
use Folklore\Contracts\Resources\Pageable as PageableContract;
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
        return data_get($this->data, 'title.'.$locale);
    }

    public function description(string $locale): ?string
    {
        return data_get($this->data, 'description.'.$locale);
    }

    public function image(string $locale): ?Image
    {
        return null;
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
