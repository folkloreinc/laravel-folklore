<?php

namespace App\Http\Controllers;

use App\Mail\TagsExport;
use App\Repositories\Concerns\WritesReferences;

class TagsController
{
    public function clear($tag)
    {
        $tag->articles()->sync([]);

        return (new TagsExport)->attach('tags.csv');
    }

    public function references(): string
    {
        return class_basename(WritesReferences::class);
    }
}
