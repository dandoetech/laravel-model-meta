<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\DTO;

final class ModelMeta
{
    /**
     * @param array<FieldMeta> $fields
     * @param array<RelationMeta> $relations
     */
    public function __construct(
        public string $name,           // "Product"
        public string $table,          // "product"
        public array $fields,          // FieldMeta[]
        public bool $timestamps = true,
        public bool $softDeletes = false,
        public array $relations = [],  // RelationMeta[]
    ) {
    }
}
