<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Services;

use DanDoeTech\LaravelModelMeta\Contracts\ModelMetaProvider;
use DanDoeTech\LaravelModelMeta\DTO\FieldMeta;
use DanDoeTech\LaravelModelMeta\DTO\ModelMeta;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MigrationGenerator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModelMetaProvider $provider,
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

            $className = 'Create' . Str::studly(Str::plural($meta->table)) . 'Table';
            $fileName  = date('Y_m_d_His') . '_create_' . Str::plural($meta->table) . '_table.php';
            $path      = database_path('migrations/' . $fileName);

            if ($this->files->exists($path) && !$force) {
                continue;
            }

            $stub = $this->buildStub($className, $meta);
            $this->files->put($path, $stub);
            $written[] = $path;

            // bump timestamp to avoid collisions
            usleep(1100000);
        }

        return $written;
    }

    private function buildStub(string $className, ModelMeta $meta): string
    {
        $up   = $this->buildCreateTable($meta);
        $down = "Schema::dropIfExists('" . addslashes(Str::plural($meta->table)) . "');";

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

    private function buildCreateTable(ModelMeta $meta): string
    {
        $tableName = addslashes(Str::plural($meta->table));
        $lines = [];
        $lines[] = "Schema::create('$tableName', function (Blueprint \$table): void {";

        $hasId = false;
        foreach ($meta->fields as $f) {
            if ($f->name === 'id' && (str_starts_with($f->type, 'id') || str_starts_with($f->type, 'int'))) {
                $lines[] = '    $table->id();';
                $hasId = true;
                continue;
            }

            if ($meta->timestamps && ($f->name === 'created_at' || $f->name === 'updated_at')) {
                continue;
            }

            if ($meta->softDeletes && ($f->name === 'deleted_at')) {
                continue;
            }

            $lines[] = '    ' . $this->columnLine($f);
        }

        if (!$hasId) {
            array_splice($lines, 1, 0, '    $table->id();');
        }

        if ($meta->timestamps) {
            $lines[] = '    $table->timestamps();';
        }
        if ($meta->softDeletes) {
            $lines[] = '    $table->softDeletes();';
        }

        foreach ($meta->fields as $f) {
            if ($f->foreignKey !== null) {
                $fk       = $f->foreignKey;
                $onDelete = $fk->onDelete ? "->onDelete('{$fk->onDelete}')" : '';
                $onUpdate = $fk->onUpdate ? "->onUpdate('{$fk->onUpdate}')" : '';
                $lines[]  = "    \$table->foreign('{$f->name}')->references('{$fk->references}')->on('{$fk->on}')" . $onDelete . $onUpdate . ';';
            }
        }

        $lines[] = '});';

        return implode("\n", $lines);
    }

    private function columnLine(FieldMeta $f): string
    {
        $base = match (true) {
            str_starts_with($f->type, 'uuid')       => "\$table->uuid('{$f->name}')",
            str_starts_with($f->type, 'string')     => "\$table->string('{$f->name}'" . (str_contains($f->type, ':') ? ', ' . (int) substr(strstr($f->type, ':'), 1) : '') . ')',
            str_starts_with($f->type, 'text')       => "\$table->text('{$f->name}')",
            str_starts_with($f->type, 'longText')   => "\$table->longText('{$f->name}')",
            str_starts_with($f->type, 'integer') || str_starts_with($f->type, 'int') => "\$table->integer('{$f->name}')",
            str_starts_with($f->type, 'bigint') || str_starts_with($f->type, 'bigInteger') => "\$table->bigInteger('{$f->name}')",
            str_starts_with($f->type, 'boolean') || str_starts_with($f->type, 'bool') => "\$table->boolean('{$f->name}')",
            str_starts_with($f->type, 'decimal')    => (function () use ($f): string {
                $param = str_contains($f->type, ':') ? substr(strstr($f->type, ':'), 1) : '10,2';
                return "\$table->decimal('{$f->name}', {$param})";
            })(),
            str_starts_with($f->type, 'float')      => "\$table->float('{$f->name}')",
            str_starts_with($f->type, 'json')       => "\$table->json('{$f->name}')",
            str_starts_with($f->type, 'datetime')   => "\$table->dateTime('{$f->name}')",
            str_starts_with($f->type, 'date')       => "\$table->date('{$f->name}')",
            str_starts_with($f->type, 'time')       => "\$table->time('{$f->name}')",
            str_starts_with($f->type, 'timestamp')  => "\$table->timestamp('{$f->name}')",
            default                                  => "\$table->string('{$f->name}')",
        };

        $suffix = '';
        if ($f->nullable) {
            $suffix .= '->nullable()';
        }
        if ($f->default !== null) {
            $def = is_string($f->default) ? "'" . addslashes($f->default) . "'" : var_export($f->default, true);
            $suffix .= "->default({$def})";
        }
        if ($f->comment !== null) {
            $suffix .= "->comment('".addslashes($f->comment)."')";
        }
        if ($f->unique) {
            $suffix .= '->unique()';
        } elseif ($f->index) {
            $suffix .= '->index()';
        }

        return $base . $suffix . ';';
    }
}
