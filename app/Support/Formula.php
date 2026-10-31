<?php

namespace App\Support;

/**
 * The calculated field's arithmetic (R-31 §16-adjacent, brief §9): +, -, *, /,
 * parentheses, numbers, and {field_key} references resolved from the submission's
 * answers. A tiny recursive-descent parser — no eval, no functions, no variables
 * beyond the braces form, and anything malformed or non-numeric evaluates to null
 * rather than guessing.
 */
class Formula
{
    private string $src = '';

    private int $pos = 0;

    /** @param array<string, float|int|string|null> $values keyed by field_key */
    public static function evaluate(?string $expression, array $values): ?float
    {
        $expression = trim((string) $expression);

        if ($expression === '' || strlen($expression) > 500) {
            return null;
        }

        // Substitute {key} references first; an unknown or non-numeric reference
        // makes the whole result null (an honest blank, never a silent zero).
        $failed = false;
        $substituted = preg_replace_callback('/\{([A-Za-z0-9_-]{1,64})\}/', function ($m) use ($values, &$failed) {
            $value = $values[$m[1]] ?? null;

            if (! is_numeric($value)) {
                $failed = true;

                return '0';
            }

            return '('.(float) $value.')';
        }, $expression);

        if ($failed || $substituted === null || preg_match('/[^0-9+\-*\/().\s]/', $substituted)) {
            return null;
        }

        $parser = new self;
        $parser->src = $substituted;
        $parser->pos = 0;

        try {
            $result = $parser->expr();
            $parser->skip();

            if ($parser->pos !== strlen($parser->src) || ! is_finite($result)) {
                return null;
            }

            return round($result, 6);
        } catch (\DivisionByZeroError|\RuntimeException) {
            return null;
        }
    }

    private function expr(): float
    {
        $value = $this->term();

        while (($op = $this->peek()) === '+' || $op === '-') {
            $this->pos++;
            $rhs = $this->term();
            $value = $op === '+' ? $value + $rhs : $value - $rhs;
        }

        return $value;
    }

    private function term(): float
    {
        $value = $this->factor();

        while (($op = $this->peek()) === '*' || $op === '/') {
            $this->pos++;
            $rhs = $this->factor();

            if ($op === '/') {
                if (abs($rhs) < 1e-12) {
                    throw new \RuntimeException('division by zero');
                }
                $value /= $rhs;
            } else {
                $value *= $rhs;
            }
        }

        return $value;
    }

    private function factor(): float
    {
        $this->skip();

        if ($this->peek() === '-') {
            $this->pos++;

            return -$this->factor();
        }

        if ($this->peek() === '(') {
            $this->pos++;
            $value = $this->expr();
            $this->skip();

            if ($this->peek() !== ')') {
                throw new \RuntimeException('unbalanced parentheses');
            }
            $this->pos++;

            return $value;
        }

        if (preg_match('/\G\s*(\d+(?:\.\d+)?)/', $this->src, $m, 0, $this->pos)) {
            $this->pos += strlen($m[0]);

            return (float) $m[1];
        }

        throw new \RuntimeException('unexpected token');
    }

    private function peek(): ?string
    {
        $this->skip();

        return $this->src[$this->pos] ?? null;
    }

    private function skip(): void
    {
        while (isset($this->src[$this->pos]) && ctype_space($this->src[$this->pos])) {
            $this->pos++;
        }
    }
}
