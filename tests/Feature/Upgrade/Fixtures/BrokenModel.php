<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model that a 1.x site may have after the upgrade: its signature doesn't match its
 * parent's, which is a fatal error when the class loads. It must not be loaded by the
 * tests' process.
 */
class BrokenModel extends Model
{
    protected $casts = [
        'data' => 'json',
    ];

    public function getKeyName(int $default): string
    {
        return 'id';
    }
}
