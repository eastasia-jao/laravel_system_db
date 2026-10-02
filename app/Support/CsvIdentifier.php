<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class CsvIdentifier
{
    public static function read(mixed $value, string $context): string
    {
        $value = trim((string) $value);
        // Accept only our literal digit-only Excel text wrapper, never evaluate formulas.
        if (preg_match('/^="([0-9]+)"$/D', $value, $match)) {
            $value = $match[1];
        }
        if (preg_match('/^[+-]?[0-9]+(?:\.[0-9]+)?e[+-]?[0-9]+$/iD', $value)) {
            throw ValidationException::withMessages([
                'file' => "$context uses scientific notation ($value). Enter the original full identifier as text and retry. No digits will be guessed.",
            ]);
        }

        return $value;
    }

    public static function write(mixed $value, string $context): string
    {
        $value = self::read($value, $context);
        if (preg_match('/^[0-9]+$/D', $value)) {
            return '="'.$value.'"';
        }

        return preg_match('/^[=+\-@\t\r\n]/', $value) ? "'".$value : $value;
    }
}
