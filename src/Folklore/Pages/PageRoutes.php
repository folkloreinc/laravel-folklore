<?php

namespace Folklore\Pages;

/**
 * The routes of pages, whose names `site.pages.routes` sets: `home`, `page` (`{page}`, the
 * page's slug) and `page_with_parent` (`{parent}/{page}`). They are localized routes of
 * laravel-locale, named `{locale}.{name}` (`fr.page`).
 */
class PageRoutes
{
    public const DEFAULTS = [
        'home' => 'home',
        'page' => 'page',
        'page_with_parent' => 'page_with_parent',
    ];

    /**
     * The name of a route of pages, without its locale: `home`, `page` or `page_with_parent`
     * by default.
     */
    public static function name(string $route): string
    {
        $name = config('site.pages.routes.'.$route);

        return is_string($name) && $name !== '' ? $name : (self::DEFAULTS[$route] ?? $route);
    }

    /**
     * The URL of the home page in a locale. Null when the site has no such route.
     */
    public static function home(string $locale, bool $absolute = true): ?string
    {
        return self::url('home', $locale, [], $absolute);
    }

    /**
     * The URL of a page from its slug, and the slug of its parent if it has one. Null when the
     * site has no such route.
     */
    public static function page(string $slug, string $locale, ?string $parentSlug = null, bool $absolute = true): ?string
    {
        return $parentSlug !== null
            ? self::url('page_with_parent', $locale, ['parent' => $parentSlug, 'page' => $slug], $absolute)
            : self::url('page', $locale, ['page' => $slug], $absolute);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private static function url(string $route, string $locale, array $parameters, bool $absolute): ?string
    {
        $name = $locale.'.'.self::name($route);
        if (! app('router')->has($name)) {
            return null;
        }

        return app('url')->route($name, $parameters, $absolute);
    }
}
