<?php

namespace Folklore\Pages;

/**
 * The routes of pages, whose names `site.pages.routes` sets: `home`, `page` (`{page}`, the
 * page's slug) and `page_with_parent` (`{parent}/{page}`). A name is looked up with the locale
 * first, `{locale}.{name}` (`fr.page`), as laravel-locale names localized routes, then without
 * it (`page`), for a site whose routes of pages aren't localized. A site without the route
 * `page_with_parent` serves the pages that have a parent at `page`: their slugs are unique
 * across parents (docs/decisions/0022).
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
     * The name of the site's route of pages in a locale: `{locale}.{name}` when the site has
     * that route, `{name}` otherwise. Null when the site has neither.
     */
    public static function routeName(string $route, string $locale): ?string
    {
        $name = self::name($route);
        $router = app('router');

        foreach ([$locale.'.'.$name, $name] as $candidate) {
            if ($router->has($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The URL of the home page in a locale. Null when the site has no such route.
     */
    public static function home(string $locale, bool $absolute = true): ?string
    {
        return self::url('home', $locale, [], $absolute);
    }

    /**
     * The URL of a page from its slug, and the slug of its parent if it has one: at the route
     * `page_with_parent`, or `page` when the site has no such route. Null when the site has
     * neither.
     */
    public static function page(string $slug, string $locale, ?string $parentSlug = null, bool $absolute = true): ?string
    {
        return $parentSlug !== null && self::routeName('page_with_parent', $locale) !== null
            ? self::url('page_with_parent', $locale, ['parent' => $parentSlug, 'page' => $slug], $absolute)
            : self::url('page', $locale, ['page' => $slug], $absolute);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private static function url(string $route, string $locale, array $parameters, bool $absolute): ?string
    {
        $name = self::routeName($route, $locale);
        if ($name === null) {
            return null;
        }

        return app('url')->route($name, $parameters, $absolute);
    }
}
