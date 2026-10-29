<?php

namespace Folklore\Upgrade\Checks;

use Folklore\Upgrade\Finding;
use Folklore\Upgrade\Site;
use Illuminate\Database\Connection;
use Throwable;

/**
 * The blocks of 1.1 pages, linked through `blocks_pivot`: 2.0 stores them in the page's
 * JSON (decisions/0009), and `folklore:upgrade` converts them.
 */
class BlocksCheck implements Check
{
    public function title(): string
    {
        return 'Block data';
    }

    public function check(Site $site): iterable
    {
        try {
            $connection = $site->db->connection();
            if (! $connection instanceof Connection || ! $connection->getSchemaBuilder()->hasTable('blocks_pivot')) {
                return;
            }
            $links = $connection->table('blocks_pivot')->count();
        } catch (Throwable $e) {
            yield Finding::info('The database could not be read for `blocks_pivot`: '.$e->getMessage());

            return;
        }

        if ($links === 0) {
            yield Finding::info('`blocks_pivot` is empty: no block data to convert.');

            return;
        }

        yield Finding::todo(
            "`blocks_pivot` links {$links} blocks to pages and blocks: 2.0 stores the blocks in the page's JSON.",
            '`folklore:upgrade` converts them, with the pages module of 2.0 (decisions/0009). Until then, keep the table.',
        );
    }
}
