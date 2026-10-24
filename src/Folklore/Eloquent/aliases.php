<?php

use Folklore\JsonData\Console\JsonReferencesRewriteCommand;
use Folklore\JsonData\Contracts\ReferenceHandler;
use Folklore\JsonData\Exceptions\InvalidJsonReference;
use Folklore\JsonData\Exceptions\ReferenceNotAllowed;
use Folklore\JsonData\JsonData;
use Folklore\JsonData\JsonReference;
use Folklore\JsonData\JsonReferenceEntry;
use Folklore\JsonData\ModelReferences;
use Folklore\JsonData\ReferenceContext;
use Folklore\JsonData\ReferenceOperation;

/*
 * The 1.x names of the JSON data classes that moved to folklore/laravel-json-data
 * (docs/decisions/0021). They are aliased when the autoloader starts, not when they are
 * first used, so that `instanceof` and `catch` match under both names. Deprecated,
 * removed in 3.0. `Folklore\Eloquent\JsonDataCast` stays a class: it keeps the macros.
 */
foreach ([
    JsonData::class => 'Folklore\Eloquent\JsonData',
    JsonReference::class => 'Folklore\Eloquent\JsonReference',
    JsonReferenceEntry::class => 'Folklore\Eloquent\JsonReferenceEntry',
    ReferenceContext::class => 'Folklore\Eloquent\ReferenceContext',
    ReferenceOperation::class => 'Folklore\Eloquent\ReferenceOperation',
    ModelReferences::class => 'Folklore\Eloquent\ModelReferences',
    InvalidJsonReference::class => 'Folklore\Eloquent\Exceptions\InvalidJsonReference',
    ReferenceNotAllowed::class => 'Folklore\Eloquent\Exceptions\ReferenceNotAllowed',
    ReferenceHandler::class => 'Folklore\Contracts\Eloquent\ReferenceHandler',
    JsonReferencesRewriteCommand::class => 'Folklore\Console\JsonReferencesRewriteCommand',
] as $class => $alias) {
    if (! class_exists($alias, false) && ! interface_exists($alias, false) && ! enum_exists($alias, false)) {
        class_alias($class, $alias);
    }
}
