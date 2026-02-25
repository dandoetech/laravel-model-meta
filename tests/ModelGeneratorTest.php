<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Tests;

use DanDoeTech\LaravelModelMeta\Services\ModelGenerator;
use DanDoeTech\ResourceRegistry\Definition\FieldDefinition;
use DanDoeTech\ResourceRegistry\Definition\FieldType;
use DanDoeTech\ResourceRegistry\Definition\RelationDefinition;
use DanDoeTech\ResourceRegistry\Definition\RelationType;
use DanDoeTech\ResourceRegistry\Registry\ArrayRegistryDriver;
use DanDoeTech\ResourceRegistry\Registry\Registry;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

final class ModelGeneratorTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = \sys_get_temp_dir() . '/lmm-test-models-' . \uniqid();
        \mkdir($this->outputDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = \glob($this->outputDir . '/*');
        if ($files !== false) {
            \array_map('unlink', $files);
        }
        @\rmdir($this->outputDir);
    }

    public function testGeneratesBasicModel(): void
    {
        $registry = $this->buildRegistry([
            'product' => [
                'label'      => 'Product',
                'timestamps' => true,
                'fields'     => [
                    new FieldDefinition('name', FieldType::String, nullable: false),
                    new FieldDefinition('price', FieldType::Float, nullable: false),
                ],
            ],
        ]);

        $generator = new ModelGenerator(new Filesystem(), $registry, outputDir: $this->outputDir);
        $written = $generator->generate();

        self::assertCount(1, $written);
        self::assertStringEndsWith('Product.php', $written[0]);

        $content = \file_get_contents($written[0]);
        self::assertIsString($content);

        self::assertStringContainsString('namespace App\\Models;', $content);
        self::assertStringContainsString('final class Product extends Model', $content);
        self::assertStringContainsString("protected \$table = 'products';", $content);
        self::assertStringContainsString("'name'", $content);
        self::assertStringContainsString("'price'", $content);
    }

    public function testGeneratedModelIsFinal(): void
    {
        $registry = $this->buildRegistry([
            'item' => [
                'label'  => 'Item',
                'fields' => [new FieldDefinition('name', FieldType::String)],
            ],
        ]);

        $generator = new ModelGenerator(new Filesystem(), $registry, outputDir: $this->outputDir);
        $generator->generate();

        $content = \file_get_contents($this->outputDir . '/Item.php');
        self::assertIsString($content);

        self::assertMatchesRegularExpression('/^final class Item extends Model$/m', $content);
    }

    public function testGeneratesSoftDeletes(): void
    {
        $registry = $this->buildRegistry([
            'post' => [
                'label'       => 'Post',
                'softDeletes' => true,
                'fields'      => [new FieldDefinition('title', FieldType::String)],
            ],
        ]);

        $generator = new ModelGenerator(new Filesystem(), $registry, outputDir: $this->outputDir);
        $generator->generate();

        $content = \file_get_contents($this->outputDir . '/Post.php');
        self::assertIsString($content);
        self::assertStringContainsString('use SoftDeletes;', $content);
        self::assertStringContainsString('use Illuminate\\Database\\Eloquent\\SoftDeletes;', $content);
    }

    public function testInfersCastsFromFieldType(): void
    {
        $registry = $this->buildRegistry([
            'item' => [
                'label'  => 'Item',
                'fields' => [
                    new FieldDefinition('count', FieldType::Integer),
                    new FieldDefinition('price', FieldType::Float),
                    new FieldDefinition('active', FieldType::Boolean),
                    new FieldDefinition('published_at', FieldType::DateTime),
                    new FieldDefinition('config', FieldType::Json),
                    new FieldDefinition('name', FieldType::String),
                ],
            ],
        ]);

        $generator = new ModelGenerator(new Filesystem(), $registry, outputDir: $this->outputDir);
        $generator->generate();

        $content = \file_get_contents($this->outputDir . '/Item.php');
        self::assertIsString($content);

        self::assertStringContainsString("'count' => 'integer'", $content);
        self::assertStringContainsString("'price' => 'float'", $content);
        self::assertStringContainsString("'active' => 'boolean'", $content);
        self::assertStringContainsString("'published_at' => 'datetime'", $content);
        self::assertStringContainsString("'config' => 'array'", $content);
        // String fields should NOT appear in casts
        self::assertStringNotContainsString("'name' => 'string'", $content);
    }

    public function testGeneratesBelongsToRelation(): void
    {
        $registry = $this->buildRegistry([
            'product' => [
                'label'  => 'Product',
                'fields' => [
                    new FieldDefinition('category_id', FieldType::Integer),
                ],
                'relations' => [
                    new RelationDefinition('category', RelationType::BelongsTo, 'category', foreignKey: 'category_id'),
                ],
            ],
        ]);

        $generator = new ModelGenerator(new Filesystem(), $registry, outputDir: $this->outputDir);
        $generator->generate();

        $content = \file_get_contents($this->outputDir . '/Product.php');
        self::assertIsString($content);
        self::assertStringContainsString('public function category()', $content);
        self::assertStringContainsString('belongsTo(', $content);
        self::assertStringContainsString('Category::class', $content);
        self::assertStringContainsString("'category_id'", $content);
    }

    public function testGeneratesHasManyRelation(): void
    {
        $registry = $this->buildRegistry([
            'category' => [
                'label'     => 'Category',
                'fields'    => [new FieldDefinition('name', FieldType::String)],
                'relations' => [
                    new RelationDefinition('products', RelationType::HasMany, 'product'),
                ],
            ],
        ]);

        $generator = new ModelGenerator(new Filesystem(), $registry, outputDir: $this->outputDir);
        $generator->generate();

        $content = \file_get_contents($this->outputDir . '/Category.php');
        self::assertIsString($content);
        self::assertStringContainsString('public function products()', $content);
        self::assertStringContainsString('hasMany(', $content);
        self::assertStringContainsString('Product::class', $content);
    }

    public function testGeneratesBelongsToManyRelation(): void
    {
        $registry = $this->buildRegistry([
            'product' => [
                'label'     => 'Product',
                'fields'    => [new FieldDefinition('name', FieldType::String)],
                'relations' => [
                    new RelationDefinition('tag', RelationType::BelongsToMany, 'tag', pivotTable: 'product_tag'),
                ],
            ],
        ]);

        $generator = new ModelGenerator(new Filesystem(), $registry, outputDir: $this->outputDir);
        $generator->generate();

        $content = \file_get_contents($this->outputDir . '/Product.php');
        self::assertIsString($content);
        self::assertStringContainsString('belongsToMany(', $content);
        self::assertStringContainsString("'product_tag'", $content);
    }

    public function testSkipsIdAndTimestampsFromFillable(): void
    {
        $registry = $this->buildRegistry([
            'product' => [
                'label'      => 'Product',
                'timestamps' => true,
                'fields'     => [
                    new FieldDefinition('id', FieldType::Integer),
                    new FieldDefinition('name', FieldType::String),
                    new FieldDefinition('created_at', FieldType::DateTime),
                    new FieldDefinition('updated_at', FieldType::DateTime),
                ],
            ],
        ]);

        $generator = new ModelGenerator(new Filesystem(), $registry, outputDir: $this->outputDir);
        $generator->generate();

        $content = \file_get_contents($this->outputDir . '/Product.php');
        self::assertIsString($content);

        // Only 'name' should be in fillable
        self::assertMatchesRegularExpression('/\$fillable\s*=\s*\[\s*\'name\'/', $content);
    }

    public function testOnlyFiltersByResourceKey(): void
    {
        $registry = $this->buildRegistry([
            'product' => [
                'label'  => 'Product',
                'fields' => [new FieldDefinition('name', FieldType::String)],
            ],
            'category' => [
                'label'  => 'Category',
                'fields' => [new FieldDefinition('name', FieldType::String)],
            ],
        ]);

        $generator = new ModelGenerator(new Filesystem(), $registry, outputDir: $this->outputDir);
        $written = $generator->generate(only: ['product']);

        self::assertCount(1, $written);
        self::assertStringEndsWith('Product.php', $written[0]);
    }

    public function testEmptyRegistryGeneratesNothing(): void
    {
        $registry = $this->buildRegistry([]);
        $generator = new ModelGenerator(new Filesystem(), $registry, outputDir: $this->outputDir);
        $written = $generator->generate();

        self::assertSame([], $written);
    }

    public function testCustomNamespace(): void
    {
        $registry = $this->buildRegistry([
            'product' => [
                'label'  => 'Product',
                'fields' => [new FieldDefinition('name', FieldType::String)],
            ],
        ]);

        $generator = new ModelGenerator(
            new Filesystem(),
            $registry,
            baseNamespace: 'App\\Domain\\Models',
            outputDir: $this->outputDir,
        );
        $generator->generate();

        $content = \file_get_contents($this->outputDir . '/Product.php');
        self::assertIsString($content);
        self::assertStringContainsString('namespace App\\Domain\\Models;', $content);
    }

    /**
     * @param array<string, array<string, mixed>> $config
     */
    private function buildRegistry(array $config): Registry
    {
        return new Registry(new ArrayRegistryDriver($config));
    }
}
