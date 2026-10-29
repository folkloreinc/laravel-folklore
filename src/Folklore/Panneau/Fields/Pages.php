<?php

namespace Folklore\Panneau\Fields;

/**
 * Pages: a multiple `Page` relation, to the pages resource by default
 * (docs/decisions/0022).
 */
class Pages extends Page
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->multiple = true;
    }
}
