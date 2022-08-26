<?php

namespace Folklore\Panneau\Fields;

use Panneau\Fields\ResourceItem;

class Page extends ResourceItem
{
    protected $query = [
        'paginated' => false,
    ];

    protected $pageTypes = [];

    public function resource(): string
    {
        return 'pages';
    }

    public function component(): string
    {
        return 'item';
    }

    public function attributes(): ?array
    {
        $query = $this->query;
        if (isset($this->pageTypes) && sizeof($this->pageTypes) > 0) {
            $query['type'] = $this->pageTypes;
        }

        return array_merge(parent::attributes(), [
            'placeholder' => trans('panneau.fields.select_page'),
            'itemLabelPath' => 'title.'.app()->getLocale(),
            'itemImagePath' => null,
            'requestQuery' => $query,
        ]);
    }

    public function withQuery($query)
    {
        $this->query = $query;
        return $this;
    }

    public function withTypes($types)
    {
        $this->pageTypes = is_array($types) ? $types : func_get_args();
        return $this;
    }
}
