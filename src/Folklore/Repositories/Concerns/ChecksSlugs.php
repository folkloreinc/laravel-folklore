<?php

namespace Folklore\Repositories\Concerns;

use Folklore\Repositories\Entities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Implements `Contracts\Repositories\ChecksSlugs` for a repository that extends the base
 * repository: the slug is looked up in the column named after its field, or `{field}_{locale}`
 * for a localized slug (`slug_fr`, as the pages module's models have them), within the
 * repository's query context, deleted entities included.
 *
 * Override `getSlugColumn()` to look up another column, or `newSlugQuery()` to narrow the
 * lookup, such as to a page's parent from the form's values.
 *
 * @phpstan-require-extends Entities
 */
trait ChecksSlugs
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function slugExists(string $field, string $value, ?string $locale = null, ?string $exceptId = null, array $values = []): bool
    {
        return $this->newSlugQuery($field, $value, $locale, $exceptId, $values)->exists();
    }

    /**
     * The query of the entities other than `$exceptId` that have this value for the slug,
     * within the repository's query context, deleted ones included: a deleted entity can
     * come back, and a unique index counts it too. The default params don't apply, as for
     * finds.
     *
     * @param  array<string, mixed>  $values  The form's values, not validated.
     * @return Builder<covariant Model>
     */
    protected function newSlugQuery(string $field, string $value, ?string $locale, ?string $exceptId, array $values): Builder
    {
        $query = $this->newQueryInContext()->withoutGlobalScope(SoftDeletingScope::class);
        $query->where($query->qualifyColumn($this->getSlugColumn($field, $locale)), $value);
        if (! is_null($exceptId)) {
            $query->where($query->qualifyColumn($this->getEntityKeyName()), '!=', $exceptId);
        }

        return $query;
    }

    /**
     * The column of a slug: the field's name, or `{field}_{locale}` for a localized slug.
     */
    protected function getSlugColumn(string $field, ?string $locale): string
    {
        return is_null($locale) ? $field : $field.'_'.$locale;
    }
}
