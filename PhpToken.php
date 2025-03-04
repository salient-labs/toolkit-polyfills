<?php

namespace Salient\Polyfill;

use Stringable;
use TypeError;

/**
 * @api
 */
class PhpToken implements Stringable
{
    private const IDENTIFIER = [
        \T_ABSTRACT => true,
        \T_ARRAY => true,
        \T_AS => true,
        \T_BREAK => true,
        \T_CALLABLE => true,
        \T_CASE => true,
        \T_CATCH => true,
        \T_CLASS => true,
        \T_CLASS_C => true,
        \T_CLONE => true,
        \T_CONST => true,
        \T_CONTINUE => true,
        \T_DECLARE => true,
        \T_DEFAULT => true,
        \T_DIR => true,
        \T_DO => true,
        \T_ECHO => true,
        \T_ELSE => true,
        \T_ELSEIF => true,
        \T_EMPTY => true,
        \T_ENDDECLARE => true,
        \T_ENDFOR => true,
        \T_ENDFOREACH => true,
        \T_ENDIF => true,
        \T_ENDSWITCH => true,
        \T_ENDWHILE => true,
        \T_EVAL => true,
        \T_EXIT => true,
        \T_EXTENDS => true,
        \T_FILE => true,
        \T_FINAL => true,
        \T_FINALLY => true,
        \T_FN => true,
        \T_FOR => true,
        \T_FOREACH => true,
        \T_FUNC_C => true,
        \T_FUNCTION => true,
        \T_GLOBAL => true,
        \T_GOTO => true,
        \T_HALT_COMPILER => true,
        \T_IF => true,
        \T_IMPLEMENTS => true,
        \T_INCLUDE => true,
        \T_INCLUDE_ONCE => true,
        \T_INSTANCEOF => true,
        \T_INSTEADOF => true,
        \T_INTERFACE => true,
        \T_ISSET => true,
        \T_LINE => true,
        \T_LIST => true,
        \T_LOGICAL_AND => true,
        \T_LOGICAL_OR => true,
        \T_LOGICAL_XOR => true,
        \T_METHOD_C => true,
        \T_NAMESPACE => true,
        \T_NEW => true,
        \T_NS_C => true,
        \T_PRINT => true,
        \T_PRIVATE => true,
        \T_PROTECTED => true,
        \T_PUBLIC => true,
        \T_REQUIRE => true,
        \T_REQUIRE_ONCE => true,
        \T_RETURN => true,
        \T_STATIC => true,
        \T_STRING => true,
        \T_SWITCH => true,
        \T_THROW => true,
        \T_TRAIT => true,
        \T_TRAIT_C => true,
        \T_TRY => true,
        \T_UNSET => true,
        \T_USE => true,
        \T_VAR => true,
        \T_WHILE => true,
        \T_YIELD => true,
    ];

    /**
     * One of the T_* constants, or an ASCII codepoint representing a
     * single-char token
     */
    public int $id;

    /**
     * The textual content of the token
     */
    public string $text;

    /**
     * The starting line number (1-based) of the token
     */
    public int $line;

    /**
     * The starting position (0-based) in the tokenized string
     */
    public int $pos;

    /**
     * Creates a new PhpToken object
     *
     * @param int $id One of the T_* constants, or an ASCII codepoint
     * representing a single-char token.
     * @param string $text The textual content of the token.
     * @param int $line The starting line number (1-based) of the token.
     * @param int $pos The starting position (0-based) in the tokenized string.
     */
    final public function __construct(
        int $id,
        string $text,
        int $line = -1,
        int $pos = -1
    ) {
        $this->id = $id;
        $this->text = $text;
        $this->line = $line;
        $this->pos = $pos;
    }

    /**
     * Get the name of the token
     *
     * @return string|null An ASCII character for single-char tokens, or one of
     * the T_* constant names for known tokens, or **`null`** for unknown
     * tokens.
     */
    public function getTokenName(): ?string
    {
        if ($this->id < 256) {
            return chr($this->id);
        }

        $name = [
            \T_NAME_FULLY_QUALIFIED => 'T_NAME_FULLY_QUALIFIED',
            \T_NAME_QUALIFIED => 'T_NAME_QUALIFIED',
            \T_NAME_RELATIVE => 'T_NAME_RELATIVE',
        ][$this->id] ?? token_name($this->id);

        if ($name === 'UNKNOWN') {
            return null;
        }

        return $name;
    }

    /**
     * Check if the token is of given kind
     *
     * @param int|string|array<int|string> $kind Either a single value to match
     * the token's id or textual content, or an array thereof.
     */
    public function is($kind): bool
    {
        if (is_int($kind)) {
            $is = $this->id === $kind;
        } elseif (is_string($kind)) {
            $is = $this->text === $kind;
        } elseif (is_array($kind)) {
            $is = false;
            foreach ($kind as $_kind) {
                if (is_int($_kind)) {
                    $value = $this->id;
                } elseif (is_string($_kind)) {
                    $value = $this->text;
                } else {
                    throw new TypeError(sprintf(
                        'Argument #1 ($kind) must only have elements of type string|int, %s given',
                        gettype($_kind),
                    ));
                }
                if ($value === $_kind) {
                    $is = true;
                    break;
                }
            }
        } else {
            throw new TypeError(sprintf(
                'Argument #1 ($kind) must be of type string|int|array, %s given',
                gettype($kind),
            ));
        }

        return $is;
    }

    /**
     * Check if the token would be ignored by the PHP parser
     */
    public function isIgnorable(): bool
    {
        // Replicates test in tokenizer.c
        return $this->id === \T_WHITESPACE
            || $this->id === \T_COMMENT
            || $this->id === \T_DOC_COMMENT
            || $this->id === \T_OPEN_TAG;
    }

    /**
     * Get the textual content of the token
     */
    public function __toString(): string
    {
        return $this->text;
    }

    /**
     * Split the given source into PHP tokens, represented by PhpToken objects
     *
     * @param string $code The PHP source to parse.
     * @param int $flags Valid flags:
     *
     * - **`TOKEN_PARSE`** - Recognises the ability to use reserved words in
     *   specific contexts.
     * @return static[] An array of PHP tokens represented by instances of
     * PhpToken or its descendants. This method returns static[] so that
     * PhpToken can be seamlessly extended.
     */
    public static function tokenize(string $code, int $flags = 0): array
    {
        $_tokens = token_get_all($code, $flags);
        $_count = count($_tokens);
        $pos = 0;
        /** @var static|null */
        $last = null;
        /** @var static[] */
        $tokens = [];
        for ($i = 0; $i < $_count; $i++) {
            $_token = $_tokens[$i];
            if (is_array($_token)) {
                $token = new static($_token[0], $_token[1], $_token[2], $pos);
                // If a comment has a trailing newline, move it to a whitespace
                // token for consistency with the native implementation
                if (
                    $token->id === \T_COMMENT
                    && substr($token->text, 0, 2) !== '/*'
                    && preg_match('/(?:\r\n|\n|\r)$/D', $token->text, $matches)
                ) {
                    $newline = $matches[0];
                    $token->text = substr($token->text, 0, -strlen($newline));
                    if (
                        $i + 1 < $_count
                        && is_array($_tokens[$i + 1])
                        && $_tokens[$i + 1][0] === \T_WHITESPACE
                    ) {
                        $_tokens[$i + 1][1] = $newline . $_tokens[$i + 1][1];
                        $_tokens[$i + 1][2]--;
                    } else {
                        $tokens[] = $token;
                        $pos += strlen($token->text);
                        $token = new static(\T_WHITESPACE, $newline, $token->line, $pos);
                    }
                } elseif ($token->id === \T_NS_SEPARATOR) {
                    // Replace namespaced names with PHP 8.0 name tokens
                    if ($last && isset(self::IDENTIFIER[$last->id])) {
                        $popLast = true;
                        $text = $last->text . $token->text;
                        $id = $last->id === \T_NAMESPACE
                            ? \T_NAME_RELATIVE
                            : \T_NAME_QUALIFIED;
                    } else {
                        $popLast = false;
                        $text = $token->text;
                        $id = \T_NAME_FULLY_QUALIFIED;
                    }
                    $lastWasSeparator = true;
                    $j = $i + 1;
                    while (
                        $j < $_count
                        && is_array($_tokens[$j])
                        && (
                            ($lastWasSeparator && isset(self::IDENTIFIER[$_tokens[$j][0]]))
                            || (!$lastWasSeparator && $_tokens[$j][0] === \T_NS_SEPARATOR)
                        )
                    ) {
                        $lastWasSeparator = !$lastWasSeparator;
                        $text .= $_tokens[$j++][1];
                    }
                    if ($lastWasSeparator) {
                        $text = substr($text, 0, -1);
                        $j--;
                    }
                    if ($j > $i + 1) {
                        if ($popLast) {
                            array_pop($tokens);
                            /** @var static $last */
                            $token->pos = $pos = $last->pos;
                        }
                        $token->id = $id;
                        $token->text = $text;
                        $i = $j - 1;
                    }
                }
            } else {
                /** @var static $last */
                $line = $last->line + preg_match_all('/\r\n|\n|\r/', $last->text);
                // The token may be `b"`, so convert the last character
                $token = new static(ord($_token[-1]), $_token, $line, $pos);
            }
            $tokens[] = $last = $token;
            $pos += strlen($token->text);
        }

        return $tokens;
    }
}
