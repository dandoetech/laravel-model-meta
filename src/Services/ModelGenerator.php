<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Services;

use DanDoeTech\LaravelModelMeta\Contracts\ModelMetaProvider;
use DanDoeTech\LaravelModelMeta\DTO\ModelMeta;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class ModelGenerator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModelMetaProvider $provider,
        private readonly string $baseNamespace = 'App\\Models',
        private readonly string $basePath = 'app/Models',
    ) {
    }

    /**
     * @param array<int,string>|null $only
     * @return array<int,string> absolute paths written
     */
    public function generate(?array $only = null, bool $force = false): array
    {
        $written = [];

        foreach ($this->provider->all() as $meta) {
            if ($only !== null && !in_array($meta->name, $only, true)) {
                continue;
            }

            $class     = Str::studly($meta->name);
            $namespace = $this->baseNamespace;
            $path      = base_path($this->basePath . '/' . $class . '.php');

            if ($this->files->exists($path) && !$force) {
                continue;
            }

            $content = $this->buildModel($namespace, $class, $meta);
            $this->ensureDir(dirname($path));
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

    private function buildModel(string $namespace, string $class, ModelMeta $meta): string
    {
        $table      = addslashes(Str::plural($meta->table));
        $fillable   = $this->inferFillable($meta);
        $castsArray = $this->inferCasts($meta);
        $relations  = $this->renderRelations($meta);

        $fillableStr = $this->renderArray($fillable);
        $castsStr    = $this->renderAssocArray($castsArray);

        $softDeletesUse = $meta->softDeletes ? "use Illuminate\\Database\\Eloquent\\SoftDeletes;" : '';
        $softDeletes    = $meta->softDeletes ? "use SoftDeletes;" : '';

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
     * @var array<int, string>
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
     * @return array<int,string>
     */
    private function inferFillable(ModelMeta $meta): array
    {
        $skip = ['id', 'created_at', 'updated_at', 'deleted_at'];
        $fillable = [];
        foreach ($meta->fields as $f) {
            if (!in_array($f->name, $skip, true)) {
                $fillable[] = $f->name;
            }
        }
        return $fillable;
    }

    /**
     * @return array<string,string>
     */
    private function inferCasts(ModelMeta $meta): array
    {
        $map = [
            'boolean'   => 'boolean',
            'bool'      => 'boolean',
            'integer'   => 'integer',
            'int'       => 'integer',
            'bigint'    => 'integer',
            'json'      => 'array',
            'datetime'  => 'datetime',
            'timestamp' => 'datetime',
            'date'      => 'date',
            'decimal'   => 'decimal:2',
            'uuid'      => 'string',
        ];

        $casts = [];
        foreach ($meta->fields as $f) {
            $base = explode(':', $f->type, 2)[0];
            if (isset($map[$base])) {
                $casts[$f->name] = $map[$base];
            }
        }

        return $casts;
    }

    /**
     * @param array<int,string> $items
     */
    private function renderArray(array $items): string
    {
        if ($items === []) {
            return '[]';
        }
        $body = implode(",\n        ", array_map(
            static fn (string $v): string => "'".addslashes($v)."'",
            $items,
        ));
        return "[\n        $body,\n    ]";
    }

    /**
     * @param array<string,string> $map
     */
    private function renderAssocArray(array $map): string
    {
        if ($map === []) {
            return '[]';
        }
        $body = implode(",\n        ", array_map(
            static fn ($k, $v): string => "'".addslashes((string)$k)."' => '".addslashes((string)$v)."'",
            array_keys($map),
            $map,
        ));
        return "[\n        $body,\n    ]";
    }

    private function renderRelations(ModelMeta $meta): string
    {
        if ($meta->relations === []) {
            return '';
        }

        $blocks = [];
        foreach ($meta->relations as $r) {
            $blocks[] = $this->relationBlock(
                method: $r->methodName,
                type: $r->type,
                target: $r->targetModelFqn,
                foreignKey: $r->foreignKey,
                relatedKey: $r->relatedKey,
                pivotTable: $r->pivotTable,
                pivotFk: $r->pivotForeignKey,
                pivotOtherFk: $r->pivotRelatedKey,
            );
        }

        return "\n" . implode("\n", $blocks) . "\n";
    }

    private function relationBlock(
        string $method,
        string $type,
        string $target,
        ?string $foreignKey,
        ?string $relatedKey,
        ?string $pivotTable,
        ?string $pivotFk,
        ?string $pivotOtherFk,
    ): string {
        $call = match ($type) {
            'belongs_to',
            'belongsTo' => "\$this->belongsTo({$target}::class" . ($foreignKey ? ", '{$foreignKey}'" . ($relatedKey ? ", '{$relatedKey}'" : '') : '') . ')',
            'has_many',
            'hasMany' => "\$this->hasMany({$target}::class" . ($foreignKey ? ", '{$foreignKey}'" . ($relatedKey ? ", '{$relatedKey}'" : '') : '') . ')',
            'has_one',
            'hasOne' => "\$this->hasOne({$target}::class" . ($foreignKey ? ", '{$foreignKey}'" . ($relatedKey ? ", '{$relatedKey}'" : '') : '') . ')',
            'belongs_to_many',
            'belongsToMany' => "\$this->belongsToMany({$target}::class" . ($pivotTable ? ", '{$pivotTable}'" . ($pivotFk ? ", '{$pivotFk}'" . ($pivotOtherFk ? ", '{$pivotOtherFk}'" : '') : '') : '') . ')',
            'morphTo' => "\$this->morphTo()",
            'morphMany' => "\$this->morphMany({$target}::class, '{$foreignKey}')",
            'morphToMany' => "\$this->morphToMany({$target}::class, '{$foreignKey}'" . ($pivotTable ? ", '{$pivotTable}'" : '') . ')',
            default => "\$this->belongsTo({$target}::class)",
        };

        return <<<PHP
    public function $method()
    {
        return $call;
    }
PHP;
    }
}
