<?php

namespace Folklore\Upgrade;

use ParseError;
use PhpToken;
use RuntimeException;

/**
 * The PHP file of a model, edited as code to move its 1.x JSON declarations to a schema:
 * it finds a method and whether it returns a literal array, replaces or removes methods,
 * comments one, swaps interfaces and adds imports, and leaves the rest of the file, its
 * comments and its layout, as it is.
 */
class ModelFile
{
    /**
     * The names that make a method's return depend on something else than its code.
     *
     * @var list<string>
     */
    protected const PARAMETERS = ['$key', '$value', '$attributes', '$attribute', '$this'];

    public function __construct(protected string $code) {}

    public function code(): string
    {
        return $this->code;
    }

    /**
     * A method declared in the file: its range, from its doc comment, attributes and
     * modifiers to its closing brace, in bytes, the position of `function`, the indentation
     * of its first line, whether its body is a single `return` of an array literal that
     * reads no parameter, no `$this` and calls nothing, and whether it reads no parameter,
     * no `$this` and no `self::`, `static::` or `parent::` (its return is the same for every
     * instance and attribute, as a loop over the locales).
     *
     * @return array{start: int, end: int, function: int, indent: string, literal: bool, static: bool}|null
     */
    public function method(string $name): ?array
    {
        $tokens = $this->tokens();
        foreach ($tokens as $index => $token) {
            if (! $token->is(T_FUNCTION)) {
                continue;
            }
            $nameIndex = $this->next($tokens, $index);
            if (is_null($nameIndex) || ! $tokens[$nameIndex]->is(T_STRING) || strcasecmp($tokens[$nameIndex]->text, $name) !== 0) {
                continue;
            }

            $open = null;
            for ($i = $nameIndex; $i < count($tokens); $i++) {
                if ($tokens[$i]->text === '{') {
                    $open = $i;
                    break;
                }
                if ($tokens[$i]->text === ';') {
                    // Abstract or in an interface
                    return null;
                }
            }
            if (is_null($open)) {
                return null;
            }
            $close = $this->matching($tokens, $open);

            $start = $index;
            for ($i = $index - 1; $i >= 0; $i--) {
                if ($tokens[$i]->is([T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT, T_DOC_COMMENT])) {
                    if (! $tokens[$i]->is(T_WHITESPACE)) {
                        $start = $i;
                    }

                    continue;
                }
                if ($tokens[$i]->text === ']' && $this->attributeStart($tokens, $i) !== null) {
                    $i = $start = (int) $this->attributeStart($tokens, $i);

                    continue;
                }
                break;
            }

            $lineStart = strrpos(substr($this->code, 0, $tokens[$start]->pos), "\n");
            $lineStart = $lineStart === false ? 0 : $lineStart + 1;
            $indent = substr($this->code, $lineStart, $tokens[$start]->pos - $lineStart);

            return [
                'start' => $tokens[$start]->pos,
                'end' => $tokens[$close]->pos + 1,
                'function' => $token->pos,
                'indent' => trim($indent) === '' ? $indent : '',
                'literal' => $this->returnsLiteral($tokens, $open, $close),
                'static' => $this->isStatic($tokens, $open, $close),
            ];
        }

        return null;
    }

    /**
     * Replaces a method with code, which is given without its first line's indentation.
     */
    public function replaceMethod(string $name, string $code): void
    {
        $method = $this->method($name) ?? throw new RuntimeException("The method {$name}() isn't in the file.");
        $this->code = substr($this->code, 0, $method['start']).$code.substr($this->code, $method['end']);
    }

    /**
     * Removes a method, with its line break and the indentation of its first line.
     */
    public function removeMethod(string $name): void
    {
        $method = $this->method($name);
        if (is_null($method)) {
            return;
        }
        $before = (string) preg_replace('/\n[ \t]*$/', '', substr($this->code, 0, $method['start']));
        $after = (string) preg_replace('/^[ \t]*\n/', '', substr($this->code, $method['end']));
        $this->code = $before.$after;
    }

    /**
     * Puts a `// TODO(folklore-3): …` line above a method, in place of the one there.
     */
    public function commentMethod(string $name, string $todo): void
    {
        $method = $this->method($name);
        if (is_null($method)) {
            return;
        }
        $comment = $method['indent'].'// TODO(folklore-3): '.$todo."\n";
        $lineStart = $method['start'] - strlen($method['indent']);
        $before = substr($this->code, 0, $lineStart);
        $before = (string) preg_replace('/[ \t]*\/\/ TODO\(folklore-3\):[^\n]*\n$/', '', $before);
        $this->code = $before.$comment.substr($this->code, $lineStart);
    }

    /**
     * Replaces interfaces of the class's `implements` with another one, written as given,
     * or adds it when the class implements none of them. Without one, removes them.
     *
     * @param  list<string>  $remove  Fully qualified names.
     */
    public function replaceInterfaces(array $remove, ?string $add = null, ?string $addFullName = null): void
    {
        $tokens = $this->tokens();
        $imports = $this->imports();
        $class = null;
        foreach ($tokens as $index => $token) {
            if ($token->is(T_CLASS) && ($previous = $this->previous($tokens, $index)) !== null && ! $tokens[$previous]->is(T_DOUBLE_COLON) && ! $tokens[$previous]->is(T_NEW)) {
                $class = $index;
                break;
            }
        }
        if (is_null($class)) {
            throw new RuntimeException('The file declares no class.');
        }

        $implements = null;
        $body = null;
        for ($i = $class; $i < count($tokens); $i++) {
            if ($tokens[$i]->is(T_IMPLEMENTS)) {
                $implements = $i;
            }
            if ($tokens[$i]->text === '{') {
                $body = $i;
                break;
            }
        }
        if (is_null($body)) {
            throw new RuntimeException('The class has no body.');
        }

        if (is_null($implements)) {
            if (is_null($add)) {
                return;
            }
            $end = $this->previous($tokens, $body) ?? $body;
            $position = $tokens[$end]->pos + strlen($tokens[$end]->text);
            $this->code = substr($this->code, 0, $position).' implements '.$add.substr($this->code, $position);

            return;
        }

        // The names of the list, as written, with their ranges
        $names = [];
        $current = null;
        for ($i = $implements + 1; $i < $body; $i++) {
            $token = $tokens[$i];
            if ($token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE])) {
                $current = ['text' => $token->text, 'start' => $token->pos, 'end' => $token->pos + strlen($token->text)];
                $names[] = $current;
            }
        }

        $kept = [];
        $hasAdded = false;
        foreach ($names as $name) {
            $full = $this->resolve($name['text'], $imports);
            if (in_array($full, $remove, true)) {
                continue;
            }
            if ($full === $addFullName) {
                $hasAdded = true;
            }
            $kept[] = $name['text'];
        }
        if (! $hasAdded && ! is_null($add)) {
            $kept[] = $add;
        }

        $first = $names[0]['start'] ?? null;
        $last = $names[count($names) - 1]['end'] ?? null;
        if (is_null($first) || is_null($last)) {
            return;
        }
        if (count($kept) === 0) {
            // ` implements A, B` goes, from the end of the name before it
            $before = $this->previous($tokens, $implements) ?? $implements;
            $first = $tokens[$before]->pos + strlen($tokens[$before]->text);
        }
        $this->code = substr($this->code, 0, $first).implode(', ', $kept).substr($this->code, $last);
    }

    /**
     * The `use` imports of classes, by their short name.
     *
     * @return array<string, string>
     */
    public function imports(): array
    {
        $imports = [];
        foreach ($this->useStatements() as $statement) {
            $imports[$statement['alias']] = $statement['name'];
        }

        return $imports;
    }

    /**
     * Imports a class, unless it is imported. Returns the name to write it with: its short
     * name, or its fully qualified name when another class has its short name.
     */
    public function import(string $class): string
    {
        $short = substr($class, (int) strrpos($class, '\\') + 1);
        $imports = $this->imports();
        if (($imports[$short] ?? null) === $class) {
            return $short;
        }
        $alias = array_search($class, $imports, true);
        if (is_string($alias)) {
            return $alias;
        }
        if (isset($imports[$short]) || $this->declaresShortName($short)) {
            return '\\'.$class;
        }

        $statements = $this->useStatements();
        $line = 'use '.$class.';';
        if (count($statements) === 0) {
            if (preg_match('/^namespace\s+[\w\\\\]+\s*;[ \t]*\n/m', $this->code, $match, PREG_OFFSET_CAPTURE) !== 1) {
                return '\\'.$class;
            }
            $position = $match[0][1] + strlen($match[0][0]);
            $this->code = substr($this->code, 0, $position)."\n".$line."\n".substr($this->code, $position);

            return $short;
        }

        // In order when the imports are sorted, after the last one otherwise
        $names = array_map(fn (array $statement) => $statement['name'], $statements);
        $sorted = $names;
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);
        $after = $statements[count($statements) - 1];
        $before = null;
        if ($sorted === $names) {
            foreach ($statements as $statement) {
                if (strcasecmp($statement['name'], $class) > 0) {
                    $before = $statement;
                    break;
                }
            }
        }
        $this->code = is_null($before)
            ? substr($this->code, 0, $after['end'])."\n".$line.substr($this->code, $after['end'])
            : substr($this->code, 0, $before['start']).$line."\n".substr($this->code, $before['start']);

        return $short;
    }

    /**
     * Removes the import of a class when the code doesn't name it otherwise.
     */
    public function removeUnusedImport(string $class): void
    {
        foreach ($this->useStatements() as $statement) {
            if ($statement['name'] !== $class) {
                continue;
            }
            $rest = substr($this->code, 0, $statement['start']).substr($this->code, $statement['end']);
            if (preg_match('/(?<![\w\\\\$])'.preg_quote($statement['alias'], '/').'(?![\w\\\\])/', $rest) === 1) {
                return;
            }
            $end = $statement['end'];
            if (($this->code[$end] ?? '') === "\n") {
                $end++;
            }
            $this->code = substr($this->code, 0, $statement['start']).substr($this->code, $end);

            return;
        }
    }

    /**
     * The fully qualified name of a class name as written in the file.
     *
     * @param  array<string, string>  $imports
     */
    public function resolve(string $name, ?array $imports = null): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }
        $imports ??= $this->imports();
        $first = explode('\\', $name)[0];
        if (isset($imports[$first])) {
            return $imports[$first].substr($name, strlen($first));
        }
        $namespace = preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $this->code, $match) === 1 ? $match[1].'\\' : '';

        return $namespace.$name;
    }

    /**
     * The top-level `use` statements of classes, one class each.
     *
     * @return list<array{name: string, alias: string, start: int, end: int}>
     */
    protected function useStatements(): array
    {
        $tokens = $this->tokens();
        $statements = [];
        $depth = 0;
        foreach ($tokens as $index => $token) {
            if ($token->text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
            } elseif ($token->text === '}') {
                $depth--;
            }
            if ($depth > 0 || ! $token->is(T_USE)) {
                continue;
            }
            $parts = [];
            $end = null;
            for ($i = $index + 1; $i < count($tokens); $i++) {
                if ($tokens[$i]->text === ';') {
                    $end = $i;
                    break;
                }
                if (! $tokens[$i]->is([T_WHITESPACE, T_COMMENT])) {
                    $parts[] = $tokens[$i]->text;
                }
            }
            if (is_null($end) || count($parts) === 0 || in_array($parts[0], ['function', 'const'], true) || in_array('{', $parts, true) || in_array(',', $parts, true)) {
                continue;
            }
            $name = ltrim($parts[0], '\\');
            $alias = count($parts) === 3 && strtolower($parts[1]) === 'as' ? $parts[2] : substr($name, (int) strrpos($name, '\\') + (str_contains($name, '\\') ? 1 : 0));
            $statements[] = ['name' => $name, 'alias' => $alias, 'start' => $token->pos, 'end' => $tokens[$end]->pos + 1];
        }

        return $statements;
    }

    protected function declaresShortName(string $short): bool
    {
        return preg_match('/^\s*(?:(?:abstract|final|readonly)\s+)*(?:class|interface|trait|enum)\s+'.preg_quote($short, '/').'\b/m', $this->code) === 1;
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    protected function returnsLiteral(array $tokens, int $open, int $close): bool
    {
        $body = [];
        for ($i = $open + 1; $i < $close; $i++) {
            if (! $tokens[$i]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                $body[] = $i;
            }
        }
        if (count($body) < 4 || ! $tokens[$body[0]]->is(T_RETURN) || $tokens[$body[count($body) - 1]]->text !== ';') {
            return false;
        }

        $first = $body[1];
        $short = $tokens[$first]->text === '[';
        if (! $short && ! ($tokens[$first]->is(T_ARRAY) && $tokens[$body[2]]->text === '(')) {
            return false;
        }
        $arrayOpen = $short ? $first : $body[2];
        // The array ends the statement
        if ($this->matching($tokens, $arrayOpen) !== $body[count($body) - 2]) {
            return false;
        }

        for ($k = 2; $k < count($body) - 1; $k++) {
            $token = $tokens[$body[$k]];
            if ($token->is(T_VARIABLE) && in_array($token->text, self::PARAMETERS, true)) {
                return false;
            }
            if ($token->is([T_NEW, T_ELLIPSIS, T_INCLUDE, T_REQUIRE, T_EVAL])) {
                return false;
            }
            // A call: a name or a variable followed by `(`
            $next = $body[$k + 1] ?? null;
            if (! is_null($next) && $tokens[$next]->text === '(' && $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_VARIABLE, T_STATIC])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    protected function isStatic(array $tokens, int $open, int $close): bool
    {
        for ($i = $open + 1; $i < $close; $i++) {
            $token = $tokens[$i];
            if ($token->is(T_VARIABLE) && in_array($token->text, self::PARAMETERS, true)) {
                return false;
            }
            if ($token->is([T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_EVAL, T_GLOBAL])) {
                return false;
            }
            $next = $this->next($tokens, $i);
            if (! is_null($next) && $tokens[$next]->is(T_DOUBLE_COLON) && ($token->is(T_STATIC) || in_array(strtolower($token->text), ['self', 'parent'], true))) {
                return false;
            }
        }

        return true;
    }

    /**
     * The index of the bracket that closes the one at `$open`.
     *
     * @param  list<PhpToken>  $tokens
     */
    protected function matching(array $tokens, int $open): int
    {
        $pairs = ['{' => '}', '[' => ']', '(' => ')'];
        $depth = 0;
        for ($i = $open; $i < count($tokens); $i++) {
            $text = $tokens[$i]->text;
            if (isset($pairs[$text]) || $tokens[$i]->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_ATTRIBUTE])) {
                $depth++;
            } elseif (in_array($text, $pairs, true)) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new RuntimeException('A bracket of the file is not closed.');
    }

    /**
     * The index of the `#[` that opens the attribute closed at `$close`, if it is one.
     *
     * @param  list<PhpToken>  $tokens
     */
    protected function attributeStart(array $tokens, int $close): ?int
    {
        for ($i = $close - 1; $i >= 0; $i--) {
            if ($tokens[$i]->is(T_ATTRIBUTE) && $this->matching($tokens, $i) === $close) {
                return $i;
            }
            if ($tokens[$i]->text === ';' || $tokens[$i]->text === '}') {
                return null;
            }
        }

        return null;
    }

    /**
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
     * @param  list<PhpToken>  $tokens
     */
    protected function previous(array $tokens, int $index): ?int
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (! $tokens[$i]->isIgnorable()) {
                return $i;
            }
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
            throw new RuntimeException('The file can\'t be parsed: '.$e->getMessage(), 0, $e);
        }
    }
}
