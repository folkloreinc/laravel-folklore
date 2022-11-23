<?php

namespace Folklore\Contracts\Resources;

use Illuminate\Database\Eloquent\Model;

interface HasModel
{
    public function getModel(): Model;
}
