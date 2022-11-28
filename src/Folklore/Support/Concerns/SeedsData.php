<?php

namespace Folklore\Support\Concerns;

use Folklore\Support\Data;

trait SeedsData
{
    public function loadJson($path)
    {
        return json_decode(file_get_contents($path), true);
    }

    public function replaceDataAtPaths($data, $paths, $replace)
    {
        return Data::reducePaths($paths, $data, function ($value, $path, $pathValue) use (
            $replace
        ) {
            $newValue = $replace($pathValue, $path, $value);
            data_set($value, $path, $newValue);
            return $value;
        });
    }
}
