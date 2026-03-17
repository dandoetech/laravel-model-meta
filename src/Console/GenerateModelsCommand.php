<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Console;

use DanDoeTech\LaravelModelMeta\Services\ModelGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class GenerateModelsCommand extends Command
{
    protected $signature = 'model-meta:generate-models
                            {--only=* : Limit to specific resource names}
                            {--force : Overwrite files if they exist}';

    protected $description = 'Generate Eloquent models from resource-registry metadata.';

    public function __construct(
        private readonly ModelGenerator $generator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var list<string> $only */
        $only = \array_values((array) $this->option('only'));
        $force = (bool) $this->option('force');

        $written = $this->generator->generate($only === [] ? null : $only, $force);

        foreach ($written as $path) {
            $this->line("Created: {$path}");
        }

        if ($written === []) {
            $this->warn('No models generated (use --force or adjust --only).');
        } else {
            Artisan::call('composer dump-autoload', [], $this->output);
        }

        return self::SUCCESS;
    }
}
