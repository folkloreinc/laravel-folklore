<?php

namespace Folklore\Support\Concerns;

use Folklore\Support\Data;

trait SeedsData
{
    public function loadJson($path)
    {
        return file_exists($path) ? json_decode(file_get_contents($path), true) : null;
    }

    public function loadCsv($path)
    {
        $items = [];
        $columns = null;

        $handle = fopen($path, 'r');
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if (is_null($columns)) {
                $columns = $data;
                continue;
            }

            $item = [];
            foreach ($data as $index => $value) {
                $item[$columns[$index]] = $value;
            }
            $items[] = $item;
        }

        fclose($handle);

        return $items;
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
