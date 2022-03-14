<?php

namespace Folklore\Composers\Concerns;

use Illuminate\Support\Arr;

trait ComposesIntl
{
    protected function composesTranslations($namespaces, $locale)
    {
        $messages = [];
        foreach ($namespaces as $namespace) {
            $texts = trans($namespace, [], $locale);
            if (is_null($texts)) {
                continue;
            }
            $texts = is_string($texts) ? [$texts] : Arr::dot($texts);
            foreach ($texts as $key => $value) {
                if (sizeof($texts) === 1 && $key === 0) {
                    $key = $namespace;
                } elseif ($namespace !== '*') {
                    $key = $namespace . '.' . $key;
                }
                $messages[$key] = preg_replace('/\:([a-z][a-z0-9\_\-]+)/', '{$1}', $value);
            }
        }
        return $messages;
    }
}
