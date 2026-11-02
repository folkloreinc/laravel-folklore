<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model on a connection that doesn't exist: looking anything up in it throws.
 */
class UnreadableModel extends Model
{
    protected $connection = 'missing';

    protected $table = 'upgrade_unreadable';
}
