<?php

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Entities\ToEntity;
use Illuminate\Database\Eloquent\Model;

if (!function_exists('array_is_list')) {
    function array_is_list(array $arr)
    {
        if ($arr === []) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

if (!function_exists('to_entity')) {
    function to_entity($entity)
    {
        if ($entity instanceof ToEntity) {
            return $entity->toEntity();
        }
        return $entity;
    }
}

if (!function_exists('to_id')) {
    function to_id($item)
    {
        if (is_numeric($item) || is_string($item)) {
            return $item;
        } elseif (is_array($item) && isset($item['id'])) {
            return $item['id'];
        } elseif ($item instanceof Model) {
            return $item->getKey();
        } elseif ($item instanceof Entity) {
            return $item->id();
        } elseif ($item instanceof ToEntity) {
            return $item->toEntity()->id();
        }
        return null;
    }
}
