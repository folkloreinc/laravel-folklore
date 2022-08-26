<?php

namespace Folklore\Panneau\Resources;

use Panneau\Contracts\ResourceType;
use Panneau\Contracts\Repository;
use Panneau\Contracts\Resource;
use Panneau\Contracts\ResourceItem;
use Folklore\Panneau\Fields\Blocks;
use JsonSerializable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class BlockWithBlocks implements ResourceType, Arrayable, Jsonable
{
    protected $type;

    protected $depth;

    public function __construct(ResourceType $type, $depth)
    {
        $this->type = $type;
        $this->depth = $depth;
    }

    public function currentDepth(): int
    {
        return $this->depth;
    }

    public function id(): string
    {
        return $this->type->id();
    }

    public function name(): string
    {
        return $this->type->name();
    }

    public function fields(): array
    {
        return collect($this->type->fields())
            ->map(function ($field) {
                return $field instanceof Blocks ? $field->currentDepth($this->depth) : $field;
            })
            ->toArray();
    }

    public function resource(): Resource
    {
        return $this->type->resource();
    }

    public function settings(): ?array
    {
        return $this->type->settings();
    }

    public function makeRepository(): ?Repository
    {
        return $this->type->makeRepository();
    }

    public function makeJsonResource(ResourceItem $item): ?JsonSerializable
    {
        return $this->type->makeJsonResource($item);
    }

    public function makeJsonCollection($resources): ?JsonSerializable
    {
        return $this->type->makeJsonCollection($resources);
    }

    public function toArray()
    {
        $id = $this->id();
        $data = [
            'id' => $this->id(),
            'name' => $this->name(),
            'fields' => collect($this->resource()->fields())
                ->filter(function ($field) use ($id) {
                    $excepTypes = $field->exceptTypes();
                    $onlyTypes = $field->onlyTypes();
                    return (is_null($excepTypes) || !in_array($id, $excepTypes)) &&
                        (is_null($onlyTypes) || in_array($id, $onlyTypes));
                })
                ->merge($this->fields())
                ->values()
                ->toArray(),
        ];

        $settings = $this->settings();
        if (isset($settings)) {
            $data['settings'] = $settings;
        }

        return $data;
    }

    public function jsonSerialize()
    {
        return $this->type->jsonSerialize();
    }

    public function toJson($options = 0)
    {
        return $this->type->toJson($options);
    }
}
