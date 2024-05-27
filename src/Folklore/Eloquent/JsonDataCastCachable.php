<?php

namespace Folklore\Eloquent;

use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Casts\ArrayObject;

class JsonDataCastCachable extends JsonDataCast implements SerializesCastableAttributes
{
    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return array
     */
    public function get($model, $key, $value, $attributes)
    {
        $value = parent::get($model, $key, $value, $attributes);

        return is_array($value) ? new ArrayObject($value, ArrayObject::ARRAY_AS_PROPS) : $value;
    }

    public function serialize($model, string $key, $value, array $attributes)
    {
        return $value->getArrayCopy();
    }
}
