<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures;

use Illuminate\Http\Request;

/**
 * The pages of the `section` type: the resource that a `Page` field relates to, to offer
 * sections only.
 */
class SectionsResource extends PagesResource
{
    public function scope(Request $request): array
    {
        return ['type' => ['section']];
    }

    public function creating(array $values, Request $request): array
    {
        return [...$values, 'type' => 'section'];
    }
}
