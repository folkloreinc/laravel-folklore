<?php

namespace Folklore\Models\Concerns;

trait SluggableWithFallback
{
    public function getSluggablesWithFallback(
        $source = 'data.title.%s',
        $column = 'slug_%s',
        $previousSource = 'data.url.%s',
        $options = []
    ) {
        $options = array_merge(
            [
                'onUpdate' => true,
                'unique' => true,
            ],
            $options
        );

        $locales = config('locale.locales');
        $fallbackLocale = config('app.fallback_locale');
        $slugs = [];

        foreach ($locales as $locale) {
            $title = data_get($this, sprintf($source, $locale));
            $slugs['slug_' . $locale] = array_merge(
                [
                    'source' => sprintf($source, !empty($title) ? $locale : $fallbackLocale),
                ],
                $options
            );
        }

        // $slugs = [];
        foreach ($locales as $locale) {
            $localesKey = collect([$locale])
                ->merge(array_diff($locales, [$locale]))
                ->values()
                ->map(function ($locale) use ($source) {
                    return sprintf($source, $locale);
                });

            // dd($localesKey);

            $slugs[sprintf($column, $locale)] = array_merge(
                [
                    'source' =>
                        $localesKey->first(function ($key) {
                            $value = data_get($this, $key);
                            return !empty($value);
                        }) ?? 'id',
                ],
                $options
            );
        }

        if ($previousSource !== null) {
            foreach ($locales as $locale) {
                $previousValue = data_get($this, sprintf($previousSource, $locale));
                if (!is_null($previousValue)) {
                    $slugs['slug_' . $locale] = array_merge($options, [
                        'source' => sprintf($previousSource, $locale),
                    ]);
                }
            }
        }

        return $slugs;
    }
}
