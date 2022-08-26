<?php

namespace Folklore\Panneau\Fields;

use Closure;
use Panneau\Fields\Text;

class PageSlug extends Text
{
    protected static $routesResolver;

    public function component(): string
    {
        return 'page-slug';
    }

    public function attributes(): ?array
    {
        $locale = app()->getLocale();
        $locales = config('locale.locales');
        $finalLocale = in_array($this->name, $locales) ? $this->name : $locale;

        return array_merge(parent::attributes(), [
            'routes' => $this->getRoutes($finalLocale),
        ]);
    }

    protected function getRoutes($locale)
    {
        if (isset(self::$routesResolver)) {
            return call_user_func(self::$routesResolver, $locale);
        }
        return [];
    }

    public static function setRoutesResolver(Closure $resolver)
    {
        self::$routesResolver = $resolver;
    }
}
