<?php

use Folklore\Rector\JsonAttributeNameRector;
use Folklore\Rector\MediaResourceSubclassRector;
use Folklore\Rector\MediasRepositoryConstructorRector;
use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;

/*
 * The upgrade of a site from laravel-folklore 1.1 to 2.0 (docs/upgrade.md, "What
 * changes"). Use it with `withSets([FolkloreSetList::UPGRADE_2_0])` once the site
 * requires 2.0 and the 2.x of laravel-mediatheque.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(RenameClassRector::class, [
        // laravel-mediatheque 2
        'Folklore\\Mediatheque\\Support\\Traits\\HasMedias' => 'Folklore\\Mediatheque\\Concerns\\HasMedias',

        // Folklore's contracts no longer extend Panneau 1.x's
        'Panneau\\Contracts\\ResourceItem' => 'Folklore\\Contracts\\Entities\\Entity',
        'Panneau\\Contracts\\Repository' => 'Folklore\\Contracts\\Repositories\\Entities',

        // The JSON data classes that moved to folklore/laravel-json-data (aliased until 3.0)
        'Folklore\\Eloquent\\JsonData' => 'Folklore\\JsonData\\JsonData',
        'Folklore\\Eloquent\\JsonReference' => 'Folklore\\JsonData\\JsonReference',
        'Folklore\\Eloquent\\JsonReferenceEntry' => 'Folklore\\JsonData\\JsonReferenceEntry',
        'Folklore\\Eloquent\\ReferenceContext' => 'Folklore\\JsonData\\ReferenceContext',
        'Folklore\\Eloquent\\ReferenceOperation' => 'Folklore\\JsonData\\ReferenceOperation',
        'Folklore\\Eloquent\\ModelReferences' => 'Folklore\\JsonData\\ModelReferences',
        'Folklore\\Eloquent\\Exceptions\\InvalidJsonReference' => 'Folklore\\JsonData\\Exceptions\\InvalidJsonReference',
        'Folklore\\Eloquent\\Exceptions\\ReferenceNotAllowed' => 'Folklore\\JsonData\\Exceptions\\ReferenceNotAllowed',
        'Folklore\\Contracts\\Eloquent\\ReferenceHandler' => 'Folklore\\JsonData\\Contracts\\ReferenceHandler',
        'Folklore\\Console\\JsonReferencesRewriteCommand' => 'Folklore\\JsonData\\Console\\JsonReferencesRewriteCommand',
    ]);

    $rectorConfig->rules([
        JsonAttributeNameRector::class,
        MediasRepositoryConstructorRector::class,
        MediaResourceSubclassRector::class,
    ]);
};
