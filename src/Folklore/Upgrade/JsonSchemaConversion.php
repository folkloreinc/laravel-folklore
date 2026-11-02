<?php

namespace Folklore\Upgrade;

/**
 * What `folklore:upgrade --schemas` does to a model that declares its JSON data with the
 * 1.x declarations: the file that replaces them with `jsonDataSchema()`, or, when they
 * can't be converted without changing what the model reads or writes, why, as a
 * `// TODO(folklore-3)` above the methods.
 */
final readonly class JsonSchemaConversion
{
    /**
     * @param  class-string  $model
     * @param  string  $path  The file, from the site's base path.
     * @param  list<string>  $methods  The 1.x methods the file declares.
     * @param  string  $code  The file once converted, or with its TODO.
     * @param  list<string>  $blockers  Why the declarations are left as they are.
     * @param  list<string>  $todos  The `TODO(folklore-3)` written in the schema.
     * @param  bool  $empty  Whether the declarations declare nothing, and are only removed.
     */
    public function __construct(
        public string $model,
        public string $path,
        public string $absolutePath,
        public int $line,
        public array $methods,
        public string $code,
        public array $blockers = [],
        public array $todos = [],
        public bool $empty = false,
    ) {}

    public function converts(): bool
    {
        return count($this->blockers) === 0;
    }

    public function location(): string
    {
        return $this->path.':'.$this->line;
    }

    /**
     * Whether writing the file changes it.
     */
    public function changes(): bool
    {
        $current = @file_get_contents($this->absolutePath);

        return ! is_string($current) || $current !== $this->code;
    }
}
