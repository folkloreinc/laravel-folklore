<?php

namespace Folklore\Contracts\Entities;

use Illuminate\Database\Eloquent\Model;

interface HasModel
{
    public function getModel(): Model;
}
