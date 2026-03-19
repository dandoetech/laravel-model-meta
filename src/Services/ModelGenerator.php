<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Services;

use DanDoeTech\ResourceRegistry\Contracts\RelationDefinitionInterface;
use DanDoeTech\ResourceRegistry\Contracts\ResourceDefinitionInterface;
use DanDoeTech\ResourceRegistry\Definition\FieldType;
use DanDoeTech\ResourceRegistry\Definition\RelationType;
use DanDoeTech\ResourceRegistry\Registry\Registry;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class ModelGenerator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Registry $registry,
        private readonly string $baseNamespace = 'App\\Models',
        private readonly string $basePath = 'app/Models',
        private readonly string $outputDir = '',
    ) {
    }

    /**
     * @param  list<string>|null $only Resource keys to generate for (null = all)
     * @return list<string>      Absolute paths written
     */
    public function generate(?array $only = null, bool $force = false): array
    {
        $written = [];

        foreach ($this->registry->all() as $resource) {
            if ($only !== null && !\in_array($resource->getKey(), $only, true)) {
                continue;
            }

            $class = Str::studly($resource->getKey());
            /** @var string $path */
            $path = $this->outputDir !== ''
                ? $this->outputDir . '/' . $class . '.php'
                : base_path($this->basePath . '/' . $class . '.php');

            if ($this->files->exists($path) && !$force) {
                continue;
            }

            $content = $this->buildModel($this->baseNamespace, $class, $resource);
            $this->ensureDir(\dirname($path));
            $this->files->put($path, $content);
            $written[] = $path;
        }

        return $written;
    }

    private function ensureDir(string $dir): void
    {
        if (!$this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0775, true);
        }
    }

    private function buildModel(string $namespace, string $class, ResourceDefinitionInterface $resource): string
    {
        $table = \addslashes(Str::plural($resource->getKey()));
        $fillable = $this->inferFillable($resource);
        $castsArray = $this->inferCasts($resource);
        $relations = $this->renderRelations($resource);

        $fillableStr = $this->renderArray($fillable);
        $castsStr = $this->renderAssocArray($castsArray);

        $softDeletesUse = $resource->usesSoftDeletes() ? 'use Illuminate\\Database\\Eloquent\\SoftDeletes;' : '';
        $softDeletes = $resource->usesSoftDeletes() ? 'use SoftDeletes;' : '';

        return <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
use Illuminate\\Database\\Eloquent\\Model;
{$softDeletesUse}

final class {$class} extends Model
{
    use HasFactory;
    {$softDeletes}

    protected \$table = '$table';

    /**
     * @var list<string>
     */
    protected \$fillable = $fillableStr;

    /**
     * @var array<string, string>
     */
    protected \$casts = $castsStr;

$relations}

PHP;
    }

    /**
     * @return list<string>
     */
    private function inferFillable(ResourceDefinitionInterface $resource): array
    {
        $skip = ['id', 'created_at', 'updated_at', 'deleted_at'];
        $fillable = [];

        foreach ($resource->getFields() as $field) {
            if (!\in_array($field->getName(), $skip, true)) {
                $fillable[] = $field->getName();
            }
        }

        return $fillable;
    }

    /**
     * @return array<string, string>
     */
    private function inferCasts(ResourceDefinitionInterface $resource): array
    {
        $casts = [];

        foreach ($resource->getFields() as $field) {
            $cast = match ($field->getType()) {
                FieldType::Boolean  => 'boolean',
                FieldType::Integer  => 'integer',
                FieldType::Float    => 'float',
                FieldType::Json     => 'array',
                FieldType::DateTime => 'datetime',
                FieldType::Date     => 'date',
                FieldType::String, FieldType::Text, FieldType::Email, FieldType::Url, FieldType::Enum => null,
            };

            if ($cast !== null) {
                $casts[$field->getName()] = $cast;
            }
        }

        return $casts;
    }

    /**
     * @param list<string> $items
     */
    private function renderArray(array $items): string
    {
        if ($items === []) {
            return '[]';
        }
        $body = \implode(",\n        ", \array_map(
            static fn (string $v): string => "'" . \addslashes($v) . "'",
            $items,
        ));

        return "[\n        $body,\n    ]";
    }

    /**
     * @param array<string, string> $map
     */
    private function renderAssocArray(array $map): string
    {
        if ($map === []) {
            return '[]';
        }
        $body = \implode(",\n        ", \array_map(
            static fn (string $k, string $v): string => "'" . \addslashes($k) . "' => '" . \addslashes($v) . "'",
            \array_keys($map),
            $map,
        ));

        return "[\n        $body,\n    ]";
    }

    private function renderRelations(ResourceDefinitionInterface $resource): string
    {
        $relations = $resource->getRelations();
        if ($relations === []) {
            return '';
        }

        $blocks = [];
        foreach ($relations as $relation) {
            $blocks[] = $this->relationBlock($relation);
        }

        return "\n" . \implode("\n", $blocks) . "\n";
    }

    private function relationBlock(RelationDefinitionInterface $relation): string
    {
        $method = $relation->getName();
        $targetFqn = '\\' . $this->baseNamespace . '\\' . Str::studly($relation->getTarget());
        $foreignKey = $relation->getForeignKey();
        $relatedKey = $relation->getRelatedKey();
        $pivotTable = $relation->getPivotTable();

        $call = match ($relation->getType()) {
            RelationType::BelongsTo => "\$this->belongsTo({$targetFqn}::class"
                . ($foreignKey ? ", '{$foreignKey}'" . ($relatedKey ? ", '{$relatedKey}'" : '') : '')
                . ')',
            RelationType::HasMany => "\$this->hasMany({$targetFqn}::class"
                . ($foreignKey ? ", '{$foreignKey}'" . ($relatedKey ? ", '{$relatedKey}'" : '') : '')
                . ')',
            RelationType::HasOne => "\$this->hasOne({$targetFqn}::class"
                . ($foreignKey ? ", '{$foreignKey}'" . ($relatedKey ? ", '{$relatedKey}'" : '') : '')
                . ')',
            RelationType::BelongsToMany => "\$this->belongsToMany({$targetFqn}::class"
                . ($pivotTable ? ", '{$pivotTable}'" : '')
                . ')',
            RelationType::MorphTo        => '$this->morphTo()',
            RelationType::MorphMany      => "\$this->morphMany({$targetFqn}::class, '{$method}')",
            RelationType::HasManyThrough => "\$this->hasManyThrough({$targetFqn}::class, /* intermediate model */)",
            RelationType::HasOneThrough  => "\$this->hasOneThrough({$targetFqn}::class, /* intermediate model */)",
        };

        return <<<PHP
    public function $method()
    {
        return $call;
    }
PHP;
    }
}
