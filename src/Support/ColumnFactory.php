<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Support;

use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;

final class ColumnFactory
{
    public static function add(Blueprint $table, string $name, string $type): void
    {
        [$base, $param] = self::split($type);

        switch ($base) {
            case 'id':
                $table->id();
                return;

            case 'uuid':
                $table->uuid($name);
                return;

            case 'string':
                $len = $param ? (int) $param : 255;
                $table->string($name, $len);
                return;

            case 'text':
                $table->text($name);
                return;

            case 'longText':
                $table->longText($name);
                return;

            case 'integer':
            case 'int':
                $table->integer($name);
                return;

            case 'bigint':
            case 'bigInteger':
                $table->bigInteger($name);
                return;

            case 'boolean':
            case 'bool':
                $table->boolean($name);
                return;

            case 'decimal':
                [$p, $s] = self::parseTwoInts($param ?? '10,2');
                $table->decimal($name, $p, $s);
                return;

            case 'float':
                $table->float($name);
                return;

            case 'json':
                $table->json($name);
                return;

            case 'datetime':
                $table->dateTime($name);
                return;

            case 'date':
                $table->date($name);
                return;

            case 'time':
                $table->time($name);
                return;

            case 'timestamp':
                $table->timestamp($name);
                return;

            default:
                throw new InvalidArgumentException("Unsupported field type: $type");
        }
    }

    private static function split(string $type): array
    {
        $parts = explode(':', $type, 2);
        return [$parts[0], $parts[1] ?? null];
    }

    private static function parseTwoInts(string $value): array
    {
        $parts = explode(',', $value, 2);
        return [
            (int) ($parts[0] ?? 10),
            (int) ($parts[1] ?? 2),
        ];
    }
}
