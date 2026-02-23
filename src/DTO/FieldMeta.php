<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\DTO;

final class FieldMeta
{
    public function __construct(
        public string $name,                 // "title"
        public string $type,                 // "string", "decimal:10,2", "json", "datetime", "uuid", "id", ...
        public bool $nullable = false,
        public bool $unique = false,
        public bool $index = false,
        public mixed $default = null,
        public ?string $comment = null,
        public ?ForeignKeyMeta $foreignKey = null,
    ) {
    }
}
