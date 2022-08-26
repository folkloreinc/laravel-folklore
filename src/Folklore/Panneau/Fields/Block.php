<?php

namespace Folklore\Panneau\Fields;

use Panneau\Fields\ResourceItem;

class Block extends ResourceItem
{
    public function resource(): string
    {
        return 'blocks';
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'placeholder' => trans('panneau.fields.select_block'),
            'itemLabelPath' => 'title',
            'itemDescriptionPath' => null,
        ]);
    }
}
