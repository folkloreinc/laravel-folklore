<?php

use Folklore\Rector\FolkloreSetList;
use Rector\Config\RectorConfig;

// As docs/upgrade.md says to run it: without `withImportNames()`
return RectorConfig::configure()
    ->withSets([FolkloreSetList::UPGRADE_2_0]);
