<?php

namespace Folklore\Support\Concerns;

use Folklore\Support\Data;
use Closure;

trait SeedsData
{
    public function loadJson($path)
    {
        return file_exists($path) ? json_decode(file_get_contents($path), true) : null;
    }

    public function loadCsv($path, ?Closure $handler = null, $firstRowIsColumns = true)
    {
        $items = [];
        $columns = null;

        $handle = fopen($path, 'r');
        $rowIndex = 0;
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if (is_null($columns) && $firstRowIsColumns) {
                $columns = $data;
                continue;
            }

            $item = [];
            foreach ($data as $index => $value) {
                $key = isset($columns) && isset($columns[$index]) ? $columns[$index] : $index;
                $item[$key] = $value;
            }
            if (isset($handler)) {
                $handler($item, $rowIndex, $data);
            }
            if (!isset($handler)) {
                $items[] = $item;
            }
            $rowIndex++;
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
