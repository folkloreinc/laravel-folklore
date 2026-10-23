<?php

namespace Folklore\Exceptions;

use RuntimeException;

/**
 * A module is enabled in `config/site.php`, but a package it needs isn't installed.
 */
class ModuleRequirementMissing extends RuntimeException
{
    public static function for(string $module, string $package): self
    {
        return new self(
            "The [{$module}] module of laravel-folklore needs {$package}: require it with Composer, or turn the module off in config/site.php (site.modules.{$module})."
        );
    }
}
