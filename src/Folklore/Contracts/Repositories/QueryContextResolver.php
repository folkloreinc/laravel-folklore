<?php

namespace Folklore\Contracts\Repositories;

/**
 * The default query context of repositories, such as the current tenant. Bound in the
 * container, it applies to every repository, before the context given with
 * `withGlobalQuery()` or `setGlobalQuery()`.
 */
interface QueryContextResolver
{
    /**
     * The params to merge into the queries of a repository. Empty when there is no
     * context, for example outside a request.
     *
     * @param  Entities<covariant \Folklore\Contracts\Entities\Entity>  $repository
     * @return array<string, mixed>
     */
    public function resolve(Entities $repository): array;
}
