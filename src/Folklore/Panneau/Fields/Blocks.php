<?php

namespace Folklore\Panneau\Fields;

use Panneau\Fields\Items;
use Panneau\Fields\ResourceItem;
use Folklore\Panneau\Resources\BlockWithBlocks;

class Blocks extends Items
{
    protected $currentDepth = 0;

    protected $maxDepth = null;

    protected $excludeTypes = [];

    public function itemField(): ?string
    {
        return Block::class;
    }

    public function attributes(): ?array
    {
        // With types
        $itemField = $this->itemField();
        $itemField = !is_null($itemField) ? resolve($itemField) : null;
        $itemResource =
            !is_null($itemField) && $itemField instanceof ResourceItem
                ? $itemField->makeResource()
                : null;
        $resourceTypes =
            !is_null($itemResource) && $itemResource->hasTypes() ? $itemResource->getTypes() : null;

        $attributes = [
            'withoutFormGroup' => true,
            'addItemLabel' => trans('panneau.fields.add_block'),
            'noItemLabel' => trans('panneau.fields.no_blocks'),
            'itemLabel' => trans('panneau.fields.block'),
            'withoutSort' => false,
        ];

        if (!is_null($resourceTypes)) {
            $attributes['types'] = $resourceTypes
                ->filter(function ($type) {
                    $key = get_class($type);
                    return !in_array($key, $this->excludeTypes);
                })
                ->map(function ($type) {
                    $hasBlocks = collect($type->fields())->contains(function ($field) {
                        return $field instanceof self;
                    });
                    if (isset($this->maxDepth) && $hasBlocks) {
                        return new BlockWithBlocks($type, $this->currentDepth + 1);
                    }
                    return $type;
                })
                ->filter(function ($type) {
                    if (isset($this->maxDepth) && $type instanceof BlockWithBlocks) {
                        return $type->currentDepth() < $this->maxDepth;
                    }
                    return true;
                })
                ->values()
                ->toArray();
        }

        return $attributes;
    }

    public function maxDepth($depth)
    {
        $this->maxDepth = $depth;
        return $this;
    }

    public function currentDepth($depth)
    {
        $this->currentDepth = $depth;
        return $this;
    }

    public function withoutType($type)
    {
        $this->excludeTypes[] = is_object($type) ? get_class($type) : $type;
        return $this;
    }
}
