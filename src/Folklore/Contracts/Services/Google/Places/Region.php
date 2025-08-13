<?php

namespace Folklore\Contracts\Services\Google\Places;

interface Region
{
    public const LEVEL1 = '1';

    public const LEVEL2 = '2';

    public const LEVEL3 = '3';

    public function parent(): ?Region;

    public function level(): ?string;

    public function bounds(): ?Bounds;
}
