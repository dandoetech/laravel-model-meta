<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\DTO;

final class RelationMeta
{
    public function __construct(
        public string $methodName,           // "owner", "roles"
        public string $type,                 // "belongsTo","hasMany","belongsToMany","hasOne","morphTo","morphMany","morphToMany"
        public string $targetModelFqn,       // \App\Models\User::class
        public ?string $foreignKey = null,   // for belongsTo/hasMany if non-standard
        public ?string $relatedKey = null,   // for belongsTo/hasOne if non-standard
        public ?string $pivotTable = null,   // for belongsToMany
        public ?string $pivotForeignKey = null,
        public ?string $pivotRelatedKey = null,
    ) {
    }
}
