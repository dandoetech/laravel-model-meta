<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\ModelMeta;

use DanDoeTech\LaravelModelMeta\Support\LaravelTypeMapper;
use DanDoeTech\OpenApiGenerator\Contracts\ModelMetaProviderInterface;
use DanDoeTech\ResourceRegistry\Definition\FieldDefinition;
use DanDoeTech\ResourceRegistry\Definition\FieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class IntrospectingModelMetaProvider implements ModelMetaProviderInterface
{
    /**
     * @param array<string, class-string<Model>> $resourceToModel
     */
    public function __construct(
        private readonly array $resourceToModel,
    ) {
    }

    /**
     * @return list<FieldDefinition>
     */
    public function fieldsFor(string $resourceKey): array
    {
        $modelClass = $this->resourceToModel[$resourceKey] ?? null;
        if ($modelClass === null) {
            return [];
        }
        if (!\is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("Configured class for '{$resourceKey}' is not an Eloquent Model.");
        }

        /** @var Model $model */
        $model = new $modelClass();

        // Try table schema first; if connection is not available (e.g., during CI) fall back to casts/attributes.
        try {
            $table = $model->getTable();
            /** @var SchemaBuilder $schema */
            $schema = Schema::connection($model->getConnectionName());
            if ($schema->hasTable($table)) {
                $columns = $schema->getColumnListing($table);
                if ($columns !== []) {
                    return $this->fromColumns($model, $columns);
                }
            }
        } catch (\Throwable $e) {
            // Ignore DB introspection errors; fall back to casts
        }

        return $this->fromCasts($model);
    }

    /**
     * @param  list<string>          $columns
     * @return list<FieldDefinition>
     */
    private function fromColumns(Model $model, array $columns): array
    {
        $casts = $model->getCasts();
        $out = [];

        foreach ($columns as $name) {
            /** @var string|null $cast */
            $cast = $casts[$name] ?? null;
            $type = LaravelTypeMapper::fromColumnGuess($name, $cast);
            $out[] = new FieldDefinition(
                name: $name,
                type: $type,
                nullable: true,
            );
        }

        return $out;
    }

    /**
     * Fallback when schema listing is not available.
     *
     * @return list<FieldDefinition>
     */
    private function fromCasts(Model $model): array
    {
        $casts = $model->getCasts();
        if ($casts === []) {
            // very minimal fallback using common Laravel timestamps + id
            $guessed = ['id' => FieldType::Integer, 'created_at' => FieldType::DateTime, 'updated_at' => FieldType::DateTime];

            return \array_map(
                fn (string $name, FieldType $t) => new FieldDefinition($name, $t, true),
                \array_keys($guessed),
                $guessed,
            );
        }

        $out = [];
        foreach ($casts as $name => $castValue) {
            $out[] = new FieldDefinition(
                name: $name,
                type: LaravelTypeMapper::fromCast(\is_string($castValue) ? $castValue : null),
                nullable: true,
            );
        }

        return $out;
    }
}
