<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\DTO;

final class ForeignKeyMeta
{
    public function __construct(
        public string $references,  // "id"
        public string $on,          // "users"
        public ?string $onDelete = null, // "cascade","restrict","set null"
        public ?string $onUpdate = null, // same
    ) {
    }
}
