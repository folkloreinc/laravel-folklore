<?php

namespace Folklore\Upgrade;

/**
 * What a check of `folklore:upgrade --check` found in a site.
 */
final readonly class Finding
{
    /**
     * @param  string  $message  What was found, and why it matters in 2.0.
     * @param  string|null  $fix  What to do about it.
     * @param  list<string>  $locations  Where: `path:line` in the site's code, or a model
     *                                   and its JSON paths.
     */
    public function __construct(
        public Severity $severity,
        public string $message,
        public ?string $fix = null,
        public array $locations = [],
    ) {}

    /**
     * @param  list<string>  $locations
     */
    public static function breaking(string $message, ?string $fix = null, array $locations = []): self
    {
        return new self(Severity::Breaking, $message, $fix, $locations);
    }

    /**
     * @param  list<string>  $locations
     */
    public static function todo(string $message, ?string $fix = null, array $locations = []): self
    {
        return new self(Severity::Todo, $message, $fix, $locations);
    }

    /**
     * @param  list<string>  $locations
     */
    public static function deprecated(string $message, ?string $fix = null, array $locations = []): self
    {
        return new self(Severity::Deprecated, $message, $fix, $locations);
    }

    /**
     * @param  list<string>  $locations
     */
    public static function info(string $message, ?string $fix = null, array $locations = []): self
    {
        return new self(Severity::Info, $message, $fix, $locations);
    }

    /**
     * @return array{severity: string, message: string, fix: string|null, locations: list<string>}
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity->value,
            'message' => $this->message,
            'fix' => $this->fix,
            'locations' => $this->locations,
        ];
    }
}
