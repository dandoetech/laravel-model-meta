<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Adapters;

use DanDoeTech\LaravelModelMeta\Contracts\ModelMetaProvider;
use DanDoeTech\LaravelModelMeta\DTO\FieldMeta;
use DanDoeTech\LaravelModelMeta\DTO\ForeignKeyMeta;
use DanDoeTech\LaravelModelMeta\DTO\ModelMeta;
use DanDoeTech\LaravelModelMeta\DTO\RelationMeta;
use DanDoeTech\LaravelModelMeta\Support\ValueGetter;
use DanDoeTech\ResourceRegistry\Registry\Registry;
use Illuminate\Support\Str;

final readonly class ResourceRegistryModelMetaProvider implements ModelMetaProvider
{
    public function __construct(
        private Registry $registry,
        private string $defaultModelNamespace = '\\App\\Models\\',
    ) {
    }

    /**
     * @return iterable<ModelMeta>
     */
    public function all(): iterable
    {
        /** @var iterable<object|array<string,mixed>> $resources */
        $resources = $this->registry->all();

        foreach ($resources as $resource) {
            $meta = $this->mapResource($resource);
            if ($meta !== null) {
                yield $meta;
            }
        }
    }

    public function get(string $name): ?ModelMeta
    {
        $resource = $this->registry->getResource($name);
        if (!$resource) {
            return null;
        }
        return $this->mapResource($resource);
    }

    /**
     * @param object|array<string,mixed> $resource
     */
    private function mapResource(object|array $resource): ?ModelMeta
    {
        $name        = (string) ValueGetter::get($resource, 'label', '');
        $table       = (string) ValueGetter::get($resource, 'key', '');
        $timestamps  = (bool) ValueGetter::get($resource, 'timestamps', true);
        $softDeletes = (bool) ValueGetter::get($resource, 'softDeletes', false);

        if ($name === '' || $table === '') {
            return null;
        }

        $table = strtolower($table);
        $table = str_replace([' ', '.'], '_', $table);
        $table = str_replace('__', '_', $table);

        /** @var iterable<object|array<string,mixed>> $fieldsRaw */
        $fieldsRaw = ValueGetter::get($resource, 'fields', []);
        $fields = [];
        foreach ($fieldsRaw as $f) {
            $fields[] = $this->mapField($f);
        }

        /** @var iterable<object|array<string,mixed>> $relsRaw */
        $relsRaw = ValueGetter::get($resource, 'relations', []);
        $relations = [];
        foreach ($relsRaw as $r) {
            $relations[] = $this->mapRelation($r, $name);
        }

        return new ModelMeta(
            name: $name,
            table: $table,
            fields: $fields,
            timestamps: $timestamps,
            softDeletes: $softDeletes,
            relations: $relations,
        );
    }

    /**
     * @param object|array<string,mixed> $f
     */
    private function mapField(object|array $f): FieldMeta
    {
        $name     = (string) ValueGetter::get($f, 'name', '');
        $type     = (string) ValueGetter::get($f, 'type', 'string')->value;
        $nullable = (bool) ValueGetter::get($f, 'nullable', false);
        $unique   = (bool) ValueGetter::get($f, 'unique', false);
        $index    = (bool) ValueGetter::get($f, 'index', false);
        $default  = ValueGetter::get($f, 'default');
        $comment  = ValueGetter::get($f, 'comment');

        $fkRaw = ValueGetter::get($f, 'foreignKey');
        $fk = null;
        if ($fkRaw !== null) {
            $fk = new ForeignKeyMeta(
                references: (string) ValueGetter::get($fkRaw, 'references', 'id'),
                on: (string) ValueGetter::get($fkRaw, 'on', ''),
                onDelete: ValueGetter::get($fkRaw, 'onDelete'),
                onUpdate: ValueGetter::get($fkRaw, 'onUpdate'),
            );
        }

        return new FieldMeta(
            name: $name,
            type: $type,
            nullable: $nullable,
            unique: $unique,
            index: $index,
            default: $default,
            comment: is_string($comment) ? $comment : null,
            foreignKey: $fk,
        );
    }

    /**
     * @param object|array<string,mixed> $r
     */
    private function mapRelation(object|array $r, string $ownerName): RelationMeta
    {
        $methodName = (string) ValueGetter::get($r, 'methodName', (string) ValueGetter::get($r, 'name', 'related'));
        $type       = (string) ValueGetter::get($r, 'type', 'belongsTo')->value;
        $target     = (string) ValueGetter::get($r, 'targetModelFqn', '');

        if ($target === '') {
            // try to resolve by name if registry only stores the target resource name:
            $targetResourceName = (string) ValueGetter::get($r, 'target', '');
            if ($targetResourceName !== '') {
                $target = $this->defaultModelNamespace . Str::studly($targetResourceName);
            }
        }

        return new RelationMeta(
            methodName: $methodName,
            type: $type,
            targetModelFqn: $target,
            foreignKey: ValueGetter::get($r, 'foreignKey'),
            relatedKey: ValueGetter::get($r, 'relatedKey'),
            pivotTable: ValueGetter::get($r, 'pivotTable'),
            pivotForeignKey: ValueGetter::get($r, 'pivotForeignKey'),
            pivotRelatedKey: ValueGetter::get($r, 'pivotRelatedKey'),
        );
    }
}
