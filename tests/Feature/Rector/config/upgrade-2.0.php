<?php

use Folklore\Rector\FolkloreSetList;
use Rector\Config\RectorConfig;

// With the optional `withImportNames()` of docs/upgrade.md: the renamed classes are imported, the old imports removed
return RectorConfig::configure()
    ->withSets([FolkloreSetList::UPGRADE_2_0])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
