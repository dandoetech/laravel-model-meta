<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Tests\Support;

use DanDoeTech\LaravelModelMeta\Support\LaravelTypeMapper;
use DanDoeTech\ResourceRegistry\Definition\FieldType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LaravelTypeMapperTest extends TestCase
{
    /** @return array<string, array{string|null, FieldType}> */
    public static function castProvider(): array
    {
        return [
            'integer'  => ['integer', FieldType::Integer],
            'int'      => ['int', FieldType::Integer],
            'decimal'  => ['decimal', FieldType::Float],
            'float'    => ['float', FieldType::Float],
            'double'   => ['double', FieldType::Float],
            'real'     => ['real', FieldType::Float],
            'bool'     => ['bool', FieldType::Boolean],
            'boolean'  => ['boolean', FieldType::Boolean],
            'datetime' => ['datetime', FieldType::DateTime],
            'date'     => ['date', FieldType::DateTime],
            'json'     => ['json', FieldType::Json],
            'null'     => [null, FieldType::String],
            'string'   => ['string', FieldType::String],
            'unknown'  => ['custom_cast', FieldType::String],
        ];
    }

    #[Test]
    #[DataProvider('castProvider')]
    public function from_cast_maps_correctly(?string $cast, FieldType $expected): void
    {
        self::assertSame($expected, LaravelTypeMapper::fromCast($cast));
    }

    #[Test]
    public function from_cast_case_insensitive(): void
    {
        self::assertSame(FieldType::Integer, LaravelTypeMapper::fromCast('INTEGER'));
        self::assertSame(FieldType::Boolean, LaravelTypeMapper::fromCast('Boolean'));
        self::assertSame(FieldType::DateTime, LaravelTypeMapper::fromCast('DateTime'));
    }

    /** @return array<string, array{string, string|null, FieldType}> */
    public static function columnGuessProvider(): array
    {
        return [
            'id column'         => ['id', null, FieldType::Integer],
            'foreign key'       => ['category_id', null, FieldType::Integer],
            'price column'      => ['price', null, FieldType::Float],
            'amount column'     => ['total_amount', null, FieldType::Float],
            'timestamp column'  => ['created_at', null, FieldType::DateTime],
            'updated_at column' => ['updated_at', null, FieldType::DateTime],
            'regular column'    => ['name', null, FieldType::String],
            'with cast'         => ['status', 'boolean', FieldType::Boolean],
        ];
    }

    #[Test]
    #[DataProvider('columnGuessProvider')]
    public function from_column_guess_maps_correctly(string $name, ?string $cast, FieldType $expected): void
    {
        self::assertSame($expected, LaravelTypeMapper::fromColumnGuess($name, $cast));
    }

    #[Test]
    public function from_column_guess_prefers_cast_over_name_heuristic(): void
    {
        // 'price' heuristic would be Float, but cast says boolean
        self::assertSame(FieldType::Boolean, LaravelTypeMapper::fromColumnGuess('price', 'boolean'));
    }
}
