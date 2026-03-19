<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Services;

use DanDoeTech\ResourceRegistry\Contracts\FieldDefinitionInterface;
use DanDoeTech\ResourceRegistry\Contracts\ResourceDefinitionInterface;
use DanDoeTech\ResourceRegistry\Definition\FieldType;
use DanDoeTech\ResourceRegistry\Definition\RelationType;
use DanDoeTech\ResourceRegistry\Registry\Registry;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MigrationGenerator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Registry $registry,
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

            $tableName = Str::plural($resource->getKey());
            $className = 'Create' . Str::studly($tableName) . 'Table';
            $fileName = \date('Y_m_d_His') . '_create_' . $tableName . '_table.php';
            if ($this->outputDir !== '') {
                $dir = $this->outputDir;
            } else {
                /** @var string $dir */
                $dir = database_path('migrations');
            }
            $path = $dir . '/' . $fileName;

            if ($this->files->exists($path) && !$force) {
                continue;
            }

            $stub = $this->buildStub($className, $resource);
            $this->files->put($path, $stub);
            $written[] = $path;

            // Bump timestamp to avoid collisions between migration files
            \usleep(1_100_000);
        }

        return $written;
    }

    private function buildStub(string $className, ResourceDefinitionInterface $resource): string
    {
        $up = $this->buildCreateTable($resource);
        $down = "Schema::dropIfExists('" . \addslashes(Str::plural($resource->getKey())) . "');";

        return <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        {$up}
    }

    public function down(): void
    {
        {$down}
    }
};
PHP;
    }

    private function buildCreateTable(ResourceDefinitionInterface $resource): string
    {
        $tableName = \addslashes(Str::plural($resource->getKey()));

        // Collect FK columns from BelongsTo relations
        $foreignKeys = $this->collectForeignKeys($resource);

        $lines = [];
        $lines[] = "Schema::create('$tableName', function (Blueprint \$table): void {";
        $lines[] = '    $table->id();';

        foreach ($resource->getFields() as $field) {
            $name = $field->getName();

            if ($name === 'id') {
                continue;
            }

            if ($resource->isTimestamped() && \in_array($name, ['created_at', 'updated_at'], true)) {
                continue;
            }

            if ($resource->usesSoftDeletes() && $name === 'deleted_at') {
                continue;
            }

            $lines[] = '    ' . $this->columnLine($field, isset($foreignKeys[$name]));
        }

        if ($resource->isTimestamped()) {
            $lines[] = '    $table->timestamps();';
        }
        if ($resource->usesSoftDeletes()) {
            $lines[] = '    $table->softDeletes();';
        }

        foreach ($foreignKeys as $column => $fk) {
            $targetTable = \addslashes($fk['on']);
            $references = $fk['references'];
            $lines[] = "    \$table->foreign('{$column}')->references('{$references}')->on('{$targetTable}');";
        }

        $lines[] = '});';

        return \implode("\n", $lines);
    }

    /**
     * @return array<string, array{references: string, on: string}>
     */
    private function collectForeignKeys(ResourceDefinitionInterface $resource): array
    {
        $foreignKeys = [];

        foreach ($resource->getRelations() as $relation) {
            if ($relation->getType() !== RelationType::BelongsTo) {
                continue;
            }

            $fkColumn = $relation->getForeignKey()
                ?? Str::snake($relation->getName()) . '_id';

            $foreignKeys[$fkColumn] = [
                'references' => $relation->getRelatedKey() ?? 'id',
                'on'         => Str::plural($relation->getTarget()),
            ];
        }

        return $foreignKeys;
    }

    private function columnLine(FieldDefinitionInterface $field, bool $isForeignKey = false): string
    {
        $name = $field->getName();

        if ($isForeignKey) {
            $base = "\$table->unsignedBigInteger('{$name}')";
        } else {
            $base = match ($field->getType()) {
                FieldType::String, FieldType::Email, FieldType::Url, FieldType::Enum => "\$table->string('{$name}')",
                FieldType::Text     => "\$table->text('{$name}')",
                FieldType::Integer  => "\$table->integer('{$name}')",
                FieldType::Float    => "\$table->float('{$name}')",
                FieldType::Boolean  => "\$table->boolean('{$name}')",
                FieldType::DateTime => "\$table->dateTime('{$name}')",
                FieldType::Date     => "\$table->date('{$name}')",
                FieldType::Json     => "\$table->json('{$name}')",
            };
        }

        $suffix = '';
        if ($field->isNullable()) {
            $suffix .= '->nullable()';
        }
        if ($field->getDefault() !== null) {
            $def = \is_string($field->getDefault())
                ? "'" . \addslashes($field->getDefault()) . "'"
                : \var_export($field->getDefault(), true);
            $suffix .= "->default({$def})";
        }
        if ($field->getComment() !== null) {
            $suffix .= "->comment('" . \addslashes($field->getComment()) . "')";
        }
        if ($field->isUnique()) {
            $suffix .= '->unique()';
        } elseif ($field->isIndexed()) {
            $suffix .= '->index()';
        }

        return $base . $suffix . ';';
    }
}
