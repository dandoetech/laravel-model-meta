<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Providers;

use DanDoeTech\LaravelModelMeta\Adapters\ResourceRegistryModelMetaProvider;
use DanDoeTech\LaravelModelMeta\Console\GenerateMigrationsCommand;
use DanDoeTech\LaravelModelMeta\Console\GenerateModelsCommand;
use DanDoeTech\LaravelModelMeta\Contracts\ModelMetaProvider;
use DanDoeTech\LaravelModelMeta\ModelMeta\IntrospectingModelMetaProvider;
use DanDoeTech\LaravelModelMeta\Services\MigrationGenerator;
use DanDoeTech\LaravelModelMeta\Services\ModelGenerator;
use DanDoeTech\LaravelOpenApiGenerator\Support\LaravelRegistryFactory;
use DanDoeTech\OpenApiGenerator\Contracts\ModelMetaProviderInterface;
use DanDoeTech\OpenApiGenerator\ModelMeta\ArrayModelMetaProvider;
use DanDoeTech\OpenApiGenerator\ModelMeta\CompositeModelMetaProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

final class ModelMetaServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/model_meta.php', 'model_meta');

        $this->app->bind(ModelMetaProviderInterface::class, function ($app) {
            $config = (array) $app['config']->get('model_meta', []);

            $arrayFields = $config['array_fields'] ?? [];
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

        $this->addGenerators();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/model_meta.php' => $this->app->configPath('model_meta.php'),
        ], 'model-meta-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateMigrationsCommand::class,
                GenerateModelsCommand::class,
            ]);
        }
    }

    public function provides(): array
    {
        return [ModelMetaProviderInterface::class];
    }

    private function addGenerators(): void
    {
        $registry = LaravelRegistryFactory::make($this->app);

        // Bind ModelMetaProvider to ResourceRegistry adapter
        $this->app->singleton(ModelMetaProvider::class, static function (Application $app) use ($registry): ModelMetaProvider {
            return new ResourceRegistryModelMetaProvider(
                $registry,
            );
        });

        $this->app->singleton(MigrationGenerator::class, static function (Application $app): MigrationGenerator {
            return new MigrationGenerator(
                $app->make(Filesystem::class),
                $app->make(ModelMetaProvider::class),
            );
        });

        $this->app->singleton(ModelGenerator::class, static function (Application $app): ModelGenerator {
            return new ModelGenerator(
                $app->make(Filesystem::class),
                $app->make(ModelMetaProvider::class),
            );
        });
    }
}
