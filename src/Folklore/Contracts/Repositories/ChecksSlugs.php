<?php

namespace Folklore\Contracts\Repositories;

/**
 * A repository that says whether a slug is taken, within its query context. The Panneau
 * plugin's adapter answers the checks of Panneau's unique slugs with it, with the resource's
 * scope as the context (`withGlobalQuery()`).
 *
 * `Repositories\Concerns\ChecksSlugs` implements it for the base repository.
 */
interface ChecksSlugs
{
    /**
     * Whether an entity other than `$exceptId` has this value for the slug.
     *
     * @param  string  $field  The name of the slug, as the form has it (`slug`).
     * @param  string|null  $locale  The locale of the value, for a localized slug. Null otherwise.
     * @param  string|null  $exceptId  The public id of the entity being edited, or null when creating one.
     * @param  array<string, mixed>  $values  The form's values, keyed by field name. They are not
     *                                        validated: use them to narrow the lookup only (a page's parent).
     */
    public function slugExists(string $field, string $value, ?string $locale = null, ?string $exceptId = null, array $values = []): bool;
}
