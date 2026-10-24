<?php

namespace Folklore\Eloquent;

use Folklore\JsonData\Schema\ConfigLocaleResolver;

/**
 * The locales of the translatable nodes of JSON data schemas, for a site: `site.locales`
 * when it is set, then laravel-locale's `locale.locales`, then laravel-json-data's own
 * config (docs/json-data.md, "Schemas"). The default locale is laravel-json-data's:
 * `json-data.default_locale`, else `app.locale` when it is one of the locales, else the
 * first one.
 */
final class SiteLocaleResolver extends ConfigLocaleResolver
{
    public function locales(): array
    {
        foreach (['site.locales', 'locale.locales'] as $key) {
            $locales = $this->config->get($key);
            $locales = is_array($locales) ? array_values(array_filter($locales, fn (mixed $locale) => is_string($locale) && $locale !== '')) : [];
            if (count($locales) > 0) {
                return $locales;
            }
        }

        return parent::locales();
    }
}
