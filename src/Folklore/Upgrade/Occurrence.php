<?php

namespace Folklore\Upgrade;

/**
 * A line of the site's code that a check matched.
 */
final readonly class Occurrence
{
    /**
     * @param  list<string>  $groups  The groups of the pattern that matched.
     */
    public function __construct(
        public SourceFile $file,
        public int $line,
        public array $groups = [],
    ) {}

    public function location(): string
    {
        return $this->file->path.':'.$this->line;
    }
}
