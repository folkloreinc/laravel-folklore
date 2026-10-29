<?php

namespace Folklore\Upgrade;

/**
 * A PHP file of the site, read line by line, without its comment lines.
 */
final readonly class SourceFile
{
    /**
     * @param  string  $path  The path from the site's base path.
     * @param  array<int, string>  $lines  The lines by number, from 1; a comment line is empty.
     */
    public function __construct(
        public string $path,
        public array $lines,
    ) {}

    public static function read(string $absolutePath, string $path): self
    {
        $contents = @file_get_contents($absolutePath);
        $lines = [];
        $inComment = false;
        foreach (preg_split('/\R/', is_string($contents) ? $contents : '') ?: [] as $index => $line) {
            $trimmed = ltrim($line);
            if ($inComment) {
                $inComment = ! str_contains($line, '*/');
                $line = '';
            } elseif (str_starts_with($trimmed, '/*')) {
                $inComment = ! str_contains($trimmed, '*/');
                $line = '';
            } elseif (str_starts_with($trimmed, '//') || (str_starts_with($trimmed, '#') && ! str_starts_with($trimmed, '#[')) || str_starts_with($trimmed, '*')) {
                $line = '';
            }
            $lines[$index + 1] = $line;
        }

        return new self($path, $lines);
    }

    /**
     * The code, without the comment lines.
     */
    public function code(): string
    {
        return implode("\n", $this->lines);
    }

    /**
     * The class, interface or trait the file declares, with its namespace.
     */
    public function declaredClass(): ?string
    {
        $code = $this->code();
        if (preg_match('/^\s*(?:(?:abstract|final|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $code, $class) !== 1) {
            return null;
        }
        $namespace = preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $code, $match) === 1 ? $match[1].'\\' : '';

        return $namespace.$class[1];
    }
}
