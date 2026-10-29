<?php

use Folklore\Rector\FolkloreSetList;
use Rector\Config\RectorConfig;

// As docs/upgrade.md says to run it: the renamed classes are imported, the old imports removed
return RectorConfig::configure()
    ->withSets([FolkloreSetList::UPGRADE_2_0])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
