<?php

namespace Folklore\Upgrade;

use ParseError;
use PhpToken;
use RuntimeException;

/**
 * A config file that returns an array, edited as code: `set()` replaces the value of a key
 * or adds it, and leaves the rest of the file, its comments and its layout, as it is. It
 * only replaces a literal (`false`, `'media'`), and only writes under an array literal: a
 * value such as `env(...)` or a variable is the site's, and `set()` refuses it.
 */
class ConfigFile
{
    public function __construct(protected string $code) {}

    public function code(): string
    {
        return $this->code;
    }

    /**
     * Sets a key, by its dotted path from the returned array, to a value.
     */
    public function set(string $key, bool|int|string|null $value): void
    {
        $tokens = $this->tokens();
        $array = $this->returnedArray($tokens);
        $segments = explode('.', $key);

        foreach ($segments as $index => $segment) {
            $entries = $this->entries($tokens, $array);
            $entry = $entries['entries'][$segment] ?? null;

            if (is_null($entry)) {
                $this->insert($tokens, $array, $entries['last'], $segment, array_slice($segments, $index + 1), $this->literal($value));

                return;
            }

            if ($index === count($segments) - 1) {
                if ($entry['value'] !== $entry['end'] || ! $this->isLiteral($tokens[$entry['value']])) {
                    throw new RuntimeException(sprintf('`%s` is not a literal value: set it yourself.', $key));
                }
                $this->replace($tokens, $entry['value'], $entry['end'], $this->literal($value));

                return;
            }

            $nested = $this->arrayAt($tokens, $entry['value']);
            if (is_null($nested)) {
                throw new RuntimeException(sprintf('`%s` is not an array literal: set `%s` yourself.', implode('.', array_slice($segments, 0, $index + 1)), $key));
            }
            $array = $nested;
        }
    }

    /**
     * The value of a key whose value is a literal (`true`, `'medias'`), or null.
     */
    public function get(string $key): bool|int|string|null
    {
        $tokens = $this->tokens();
        $array = $this->returnedArray($tokens);
        $segments = explode('.', $key);
        foreach ($segments as $index => $segment) {
            $entry = $this->entries($tokens, $array)['entries'][$segment] ?? null;
            if (is_null($entry)) {
                return null;
            }
            if ($index < count($segments) - 1) {
                $array = $this->arrayAt($tokens, $entry['value']);
                if (is_null($array)) {
                    return null;
                }

                continue;
            }

            $token = $tokens[$entry['value']];
            if ($entry['value'] !== $entry['end']) {
                return null;
            }

            return match (true) {
                $token->is(T_CONSTANT_ENCAPSED_STRING) => $this->string($token),
                $token->is(T_LNUMBER) => (int) $token->text,
                default => match (strtolower($token->text)) {
                    'true' => true,
                    'false' => false,
                    default => null,
                },
            };
        }

        return null;
    }

    /**
     * @return list<PhpToken>
     */
    protected function tokens(): array
    {
        try {
            return array_values(PhpToken::tokenize($this->code, TOKEN_PARSE));
        } catch (ParseError $e) {
            throw new RuntimeException('The config file does not parse: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * The open and close tokens of the array that the file returns.
     *
     * @param  list<PhpToken>  $tokens
     * @return array{int, int}
     */
    protected function returnedArray(array $tokens): array
    {
        foreach ($tokens as $index => $token) {
            if ($token->is(T_RETURN)) {
                $array = $this->arrayAt($tokens, $this->next($tokens, $index));
                if (! is_null($array)) {
                    return $array;
                }
            }
        }

        throw new RuntimeException('The config file does not return an array literal.');
    }

    /**
     * The open and close tokens of the array literal that starts at a token, or null.
     *
     * @param  list<PhpToken>  $tokens
     * @return array{int, int}|null
     */
    protected function arrayAt(array $tokens, ?int $index): ?array
    {
        if (is_null($index) || ! isset($tokens[$index])) {
            return null;
        }
        $open = $index;
        if ($tokens[$index]->is(T_ARRAY)) {
            $open = $this->next($tokens, $index);
            if (is_null($open) || $tokens[$open]->text !== '(') {
                return null;
            }
        } elseif ($tokens[$index]->text !== '[') {
            return null;
        }

        $depth = 0;
        for ($i = $open; $i < count($tokens); $i++) {
            $text = $tokens[$i]->text;
            if ($this->opens($tokens[$i])) {
                $depth++;
            } elseif (in_array($text, [']', ')', '}'], true)) {
                $depth--;
                if ($depth === 0) {
                    return [$open, $i];
                }
            }
        }

        return null;
    }

    /**
     * The entries of an array with a string key, each with the first and last tokens of
     * its value, and the last token of the array that isn't whitespace or a comment.
     *
     * @param  list<PhpToken>  $tokens
     * @param  array{int, int}  $array
     * @return array{entries: array<string, array{value: int, end: int}>, last: int}
     */
    protected function entries(array $tokens, array $array): array
    {
        [$open, $close] = $array;
        $entries = [];
        $last = $open;
        $depth = 0;
        $key = null;
        $value = null;
        $end = null;
        for ($i = $open + 1; $i < $close; $i++) {
            $token = $tokens[$i];
            if ($token->isIgnorable()) {
                continue;
            }
            $last = $i;
            if ($depth === 0 && $token->text === ',') {
                if (! is_null($key) && ! is_null($value) && ! is_null($end)) {
                    $entries[$key] = ['value' => $value, 'end' => $end];
                }
                $key = $value = $end = null;

                continue;
            }
            if ($depth === 0 && is_null($key) && $token->is(T_CONSTANT_ENCAPSED_STRING)) {
                $arrow = $this->next($tokens, $i);
                if (! is_null($arrow) && $tokens[$arrow]->is(T_DOUBLE_ARROW)) {
                    $key = $this->string($token);
                    $value = $this->next($tokens, $arrow);
                    $last = $i = $arrow;

                    continue;
                }
            }
            if ($this->opens($token)) {
                $depth++;
            } elseif (in_array($token->text, [']', ')', '}'], true)) {
                $depth--;
            }
            $end = $i;
        }
        if (! is_null($key) && ! is_null($value) && ! is_null($end)) {
            $entries[$key] = ['value' => $value, 'end' => $end];
        }

        return ['entries' => $entries, 'last' => $last];
    }

    /**
     * Adds a key, with the keys under it, at the end of an array, on its own lines.
     *
     * @param  list<PhpToken>  $tokens
     * @param  array{int, int}  $array
     * @param  int  $last  The last token of the array that isn't whitespace or a comment.
     * @param  list<string>  $children  The keys under it, each in the one before.
     */
    protected function insert(array $tokens, array $array, int $last, string $key, array $children, string $literal): void
    {
        [$open, $close] = $array;
        $outer = $this->indentation($tokens[$open]->pos);
        $inner = $this->innerIndentation($tokens, $array) ?? $outer.'    ';
        $line = "\n".$inner.$this->entry($key, $children, $literal, $inner).',';

        if ($last === $open) {
            // An empty array: its closing bracket goes on a line of its own
            $this->replace($tokens, $open + 1, $close - 1, $line."\n".$outer, true);

            return;
        }

        $position = $tokens[$last]->pos + strlen($tokens[$last]->text);
        $comma = $tokens[$last]->text === ',' ? '' : ',';
        if (! str_contains(substr($this->code, $position, $tokens[$close]->pos - $position), "\n")) {
            // An array on one line stays on one line
            $comma = $tokens[$last]->text === ',' ? ' ' : ', ';
            $line = $this->entry($key, $children, $literal, $outer);
            $this->code = substr($this->code, 0, $position).$comma.$line.substr($this->code, $position);

            return;
        }

        // A comment at the end of the last entry's line stays on that line
        $lineEnd = $position;
        for ($i = $last + 1; $i < $close; $i++) {
            if ($tokens[$i]->is(T_WHITESPACE) && ! str_contains($tokens[$i]->text, "\n")) {
                continue;
            }
            if ($tokens[$i]->is(T_COMMENT) && ! str_contains(rtrim($tokens[$i]->text), "\n")) {
                $lineEnd = $tokens[$i]->pos + strlen(rtrim($tokens[$i]->text));
            }
            break;
        }
        $this->code = substr($this->code, 0, $position).$comma
            .substr($this->code, $position, $lineEnd - $position).$line
            .substr($this->code, $lineEnd);
    }

    /**
     * The code of an entry, and of the arrays under it for the keys that follow.
     *
     * @param  list<string>  $children
     */
    protected function entry(string $key, array $children, string $literal, string $indentation): string
    {
        $child = array_shift($children);
        if (is_null($child)) {
            return $this->literal($key).' => '.$literal;
        }

        return $this->literal($key)." => [\n".$indentation.'    '.$this->entry($child, $children, $literal, $indentation.'    ').",\n".$indentation.']';
    }

    /**
     * Replaces the code of the tokens from `$from` to `$to`, or between them when they are
     * empty.
     *
     * @param  list<PhpToken>  $tokens
     */
    protected function replace(array $tokens, int $from, int $to, string $code, bool $between = false): void
    {
        $start = $between ? $tokens[$from - 1]->pos + strlen($tokens[$from - 1]->text) : $tokens[$from]->pos;
        $end = $between ? $tokens[$to + 1]->pos : $tokens[$to]->pos + strlen($tokens[$to]->text);

        $this->code = substr($this->code, 0, $start).$code.substr($this->code, $end);
    }

    /**
     * The indentation of the entries of an array that has some on their own lines.
     *
     * @param  list<PhpToken>  $tokens
     * @param  array{int, int}  $array
     */
    protected function innerIndentation(array $tokens, array $array): ?string
    {
        for ($i = $array[0] + 1; $i < $array[1]; $i++) {
            if (! $tokens[$i]->is(T_WHITESPACE)) {
                $indentation = $this->indentation($tokens[$i]->pos);
                $lineStart = strrpos(substr($this->code, 0, $tokens[$i]->pos), "\n");
                if ($lineStart !== false && $lineStart + 1 + strlen($indentation) === $tokens[$i]->pos) {
                    return $indentation;
                }

                return null;
            }
        }

        return null;
    }

    /**
     * The whitespace that starts the line of a position.
     */
    protected function indentation(int $position): string
    {
        $lineStart = strrpos(substr($this->code, 0, $position), "\n");
        $line = substr($this->code, $lineStart === false ? 0 : $lineStart + 1);

        return preg_match('/^[ \t]*/', $line, $match) === 1 ? $match[0] : '';
    }

    /**
     * The next token that isn't whitespace or a comment.
     *
     * @param  list<PhpToken>  $tokens
     */
    protected function next(array $tokens, int $index): ?int
    {
        for ($i = $index + 1; $i < count($tokens); $i++) {
            if (! $tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Whether a token is a literal: a string, a number, `true`, `false` or `null`.
     */
    protected function isLiteral(PhpToken $token): bool
    {
        return $token->is([T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER])
            || ($token->is(T_STRING) && in_array(strtolower($token->text), ['true', 'false', 'null'], true));
    }

    protected function opens(PhpToken $token): bool
    {
        return in_array($token->text, ['[', '(', '{'], true) || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]);
    }

    /**
     * The value of a string literal.
     */
    protected function string(PhpToken $token): string
    {
        $quote = $token->text[0];
        $value = substr($token->text, 1, -1);

        return $quote === "'" ? str_replace(['\\\\', "\\'"], ['\\', "'"], $value) : stripcslashes($value);
    }

    protected function literal(bool|int|string|null $value): string
    {
        return match (true) {
            is_null($value) => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            default => "'".addcslashes($value, "'\\")."'",
        };
    }
}
