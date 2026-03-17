<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Providers;

use DanDoeTech\LaravelModelMeta\Console\GenerateMigrationsCommand;
use DanDoeTech\LaravelModelMeta\Console\GenerateModelsCommand;
use DanDoeTech\LaravelModelMeta\ModelMeta\IntrospectingModelMetaProvider;
use DanDoeTech\LaravelModelMeta\Services\MigrationGenerator;
use DanDoeTech\LaravelModelMeta\Services\ModelGenerator;
use DanDoeTech\OpenApiGenerator\Contracts\ModelMetaProviderInterface;
use DanDoeTech\OpenApiGenerator\ModelMeta\ArrayModelMetaProvider;
use DanDoeTech\OpenApiGenerator\ModelMeta\CompositeModelMetaProvider;
use DanDoeTech\ResourceRegistry\Registry\Registry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

final class ModelMetaServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/ddt_model_meta.php', 'ddt_model_meta');

        $this->app->bind(ModelMetaProviderInterface::class, function (Application $app) {
            /** @var \Illuminate\Contracts\Config\Repository $configRepo */
            $configRepo = $app->make('config');
            /** @var array{array_fields?: mixed, resource_to_model?: mixed, provider_order?: mixed} $config */
            $config = (array) $configRepo->get('ddt_model_meta', []);

            /** @var array<string, list<\DanDoeTech\ResourceRegistry\Contracts\FieldDefinitionInterface>> $arrayFields */
            $arrayFields = $config['array_fields'] ?? [];
            /** @var array<string, class-string<\Illuminate\Database\Eloquent\Model>> $resourceToModel */
            $resourceToModel = $config['resource_to_model'] ?? [];
            $order = (array) ($config['provider_order'] ?? [
                ArrayModelMetaProvider::class,
                IntrospectingModelMetaProvider::class,
            ]);

            $providers = [];
            foreach ($order as $class) {
                if ($class === ArrayModelMetaProvider::class) {
                    $providers[] = new ArrayModelMetaProvider($arrayFields);
                } elseif ($class === IntrospectingModelMetaProvider::class) {
                    $providers[] = new IntrospectingModelMetaProvider($resourceToModel);
                }
            }

            return new CompositeModelMetaProvider($providers);
        });

        $this->app->singleton(MigrationGenerator::class, static function (Application $app): MigrationGenerator {
            return new MigrationGenerator(
                $app->make(Filesystem::class),
                $app->make(Registry::class),
            );
        });

        $this->app->singleton(ModelGenerator::class, static function (Application $app): ModelGenerator {
            return new ModelGenerator(
                $app->make(Filesystem::class),
                $app->make(Registry::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/ddt_model_meta.php' => $this->app->configPath('ddt_model_meta.php'),
        ], 'model-meta-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateMigrationsCommand::class,
                GenerateModelsCommand::class,
            ]);
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            ModelMetaProviderInterface::class,
            MigrationGenerator::class,
            ModelGenerator::class,
        ];
    }
}
