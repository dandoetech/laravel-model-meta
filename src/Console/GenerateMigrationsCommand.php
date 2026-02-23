<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Console;

use DanDoeTech\LaravelModelMeta\Services\MigrationGenerator;
use Illuminate\Console\Command;

final class GenerateMigrationsCommand extends Command
{
    protected $signature = 'model-meta:generate-migrations
                            {--only=* : Limit to specific resource names}
                            {--force : Overwrite files if they exist}';

    protected $description = 'Generate migration files from resource-registry metadata.';

    public function __construct(
        private readonly MigrationGenerator $generator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var array<int,string> $only */
        $only = (array) $this->option('only');
        $force = (bool) $this->option('force');

        $written = $this->generator->generate($only === [] ? null : $only, $force);

        foreach ($written as $path) {
            $this->line("Created: $path");
        }

        if ($written === []) {
            $this->warn('No migrations generated (use --force or adjust --only).');
        }

        return self::SUCCESS;
    }
}
