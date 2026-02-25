<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Support;

use DanDoeTech\ResourceRegistry\Definition\FieldType;

/**
 * Maps Laravel column names/casts to ResourceRegistry FieldType.
 * This mapping is intentionally simple and conservative.
 */
final class LaravelTypeMapper
{
    public static function fromCast(?string $cast): FieldType
    {
        $c = \strtolower((string) $cast);

        return match (true) {
            \str_contains($c, 'int') => FieldType::Integer,
            $c === 'decimal', => FieldType::Float,
            $c === 'float', => FieldType::Float,
            $c === 'double', => FieldType::Float,
            $c === 'real', => FieldType::Float,
            $c === 'bool', => FieldType::Boolean,
            $c === 'boolean', => FieldType::Boolean,
            $c === 'datetime', => FieldType::DateTime,
            $c === 'date', => FieldType::DateTime,
            $c === 'json', => FieldType::Json,
            default => FieldType::String,
        };
    }

    public static function fromColumnGuess(string $name, ?string $cast): FieldType
    {
        if ($cast !== null) {
            return self::fromCast($cast);
        }

        // Heuristics based on common column names
        $n = \strtolower($name);

        return match (true) {
            $n === 'id' || \str_ends_with($n, '_id')                  => FieldType::Integer,
            \str_contains($n, 'price') || \str_contains($n, 'amount') => FieldType::Float,
            \str_ends_with($n, '_at')                                 => FieldType::DateTime,
            default                                                   => FieldType::String,
        };
    }
}
