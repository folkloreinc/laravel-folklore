<?php

namespace Folklore\Panneau\Fields;

use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Repositories\Pages as PagesRepository;
use Folklore\Pages\PageRoutes;
use Panneau\Fields\Slug;

// Without Panneau 5, as while a site upgrades from 1.1 with laravel-panneau 1.x, this class
// can't load: a stub keeps 1.x's `setRoutesResolver()`, which the 1.x admin calls at boot
if (! class_exists(Slug::class)) {
    class_alias(LegacyPageSlug::class, PageSlug::class);

    return;
}

/**
 * The slug of a page: Panneau's `slug`, localized, from the `title`, whose check shows the
 * page's URL from the routes of the pages module (docs/decisions/0022).
 *
 * The URL follows the page's parent, the `parent` field of the form (`parentField()`), and the
 * home page has no slug: the field is disabled for it. The parent is found through the pages
 * repository, outside the resource's scope: it only gives the URL.
 *
 * The slug is unique: no other item of the resource has it, through the repository's
 * `slugExists()` (`ChecksSlugs`), which the pages repository checks across the types of
 * pages. `notUnique()` turns it off, for a repository without it.
 */
class PageSlug extends Slug
{
    protected ?string $parentField = 'parent';

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->localized = true;
        $this->from = 'title';
        $this->unique = true;
        $this->url = fn (string $slug, ?string $locale, array $values, ?string $id): ?string => $this->pageUrl($slug, $locale ?? app()->getLocale(), $values);
        $this->disabledWhen = fn (array $values, ?string $id): bool => $this->isHome($values, $id);
    }

    /**
     * Doesn't check that the slug is unique.
     */
    public function notUnique(): static
    {
        $this->unique = false;
        $this->uniqueScope = null;

        return $this;
    }

    /**
     * The field of the form that has the page's parent, or null for pages without a parent.
     */
    public function parentField(?string $field): static
    {
        $this->parentField = $field;

        return $this;
    }

    /**
     * The slugs of an item with a `slug($locale)` method, such as a page, in Panneau's locales.
     */
    public function resolve(object $item): mixed
    {
        if ($this->resolveUsing !== null || ! method_exists($item, 'slug')) {
            return parent::resolve($item);
        }
        if (! $this->localized) {
            return $this->serialize($item->slug(app()->getLocale()));
        }

        $slugs = [];
        foreach (self::locales() as $locale) {
            $slugs[$locale] = $item->slug($locale);
        }

        return $this->serialize($slugs);
    }

    /**
     * The URL of a page with this slug: under its parent's slug when it has one.
     *
     * @param  array<array-key, mixed>  $values
     */
    protected function pageUrl(string $slug, string $locale, array $values): ?string
    {
        $parentId = $this->parentField !== null ? self::idOf($values[$this->parentField] ?? null) : null;
        if ($parentId === null) {
            return PageRoutes::page($slug, $locale);
        }

        $parentSlug = $this->findPage($parentId)?->slug($locale);

        return $parentSlug !== null && $parentSlug !== '' ? PageRoutes::page($slug, $locale, $parentSlug) : null;
    }

    /**
     * Whether the form is the home page's: its `handle` is `home`.
     *
     * @param  array<array-key, mixed>  $values
     */
    protected function isHome(array $values, ?string $id): bool
    {
        if (array_key_exists('handle', $values)) {
            return $values['handle'] === 'home';
        }

        return $id !== null && $this->findPage($id)?->handle() === 'home';
    }

    protected function findPage(string $id): ?PageContract
    {
        return app()->bound(PagesRepository::class) ? app(PagesRepository::class)->findById($id) : null;
    }

    /**
     * @return list<string>
     */
    private static function locales(): array
    {
        $locales = config('panneau.locales');

        $locales = is_array($locales) ? array_values(array_filter($locales, is_string(...))) : [];

        return count($locales) > 0 ? $locales : [app()->getLocale()];
    }

    private static function idOf(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['id'] ?? null;
        }

        return is_int($value) || (is_string($value) && $value !== '') ? (string) $value : null;
    }
}
