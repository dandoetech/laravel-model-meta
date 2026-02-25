<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Tests;

use DanDoeTech\LaravelModelMeta\Services\MigrationGenerator;
use DanDoeTech\ResourceRegistry\Definition\FieldDefinition;
use DanDoeTech\ResourceRegistry\Definition\FieldType;
use DanDoeTech\ResourceRegistry\Definition\RelationDefinition;
use DanDoeTech\ResourceRegistry\Definition\RelationType;
use DanDoeTech\ResourceRegistry\Registry\ArrayRegistryDriver;
use DanDoeTech\ResourceRegistry\Registry\Registry;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

final class MigrationGeneratorTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = \sys_get_temp_dir() . '/lmm-test-migrations-' . \uniqid();
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

    public function testGeneratesBasicMigration(): void
    {
        $registry = $this->buildRegistry([
            'product' => [
                'label'      => 'Product',
                'timestamps' => true,
                'fields'     => [
                    new FieldDefinition('name', FieldType::String, nullable: false),
                    new FieldDefinition('price', FieldType::Float, nullable: false),
                    new FieldDefinition('active', FieldType::Boolean),
                ],
            ],
        ]);

        $generator = new MigrationGenerator(new Filesystem(), $registry, $this->outputDir);
        $written = $generator->generate();

        self::assertCount(1, $written);
        $content = \file_get_contents($written[0]);
        self::assertIsString($content);

        self::assertStringContainsString("Schema::create('products'", $content);
        self::assertStringContainsString('$table->id()', $content);
        self::assertStringContainsString("\$table->string('name')", $content);
        self::assertStringContainsString("\$table->float('price')", $content);
        self::assertStringContainsString("\$table->boolean('active')", $content);
        self::assertStringContainsString('$table->timestamps()', $content);
        self::assertStringContainsString("Schema::dropIfExists('products')", $content);
    }

    public function testGeneratesSoftDeletes(): void
    {
        $registry = $this->buildRegistry([
            'post' => [
                'label'       => 'Post',
                'softDeletes' => true,
                'fields'      => [
                    new FieldDefinition('title', FieldType::String),
                ],
            ],
        ]);

        $generator = new MigrationGenerator(new Filesystem(), $registry, $this->outputDir);
        $written = $generator->generate();

        $content = \file_get_contents($written[0]);
        self::assertIsString($content);
        self::assertStringContainsString('$table->softDeletes()', $content);
    }

    public function testGeneratesAllFieldTypes(): void
    {
        $registry = $this->buildRegistry([
            'item' => [
                'label'  => 'Item',
                'fields' => [
                    new FieldDefinition('name', FieldType::String),
                    new FieldDefinition('count', FieldType::Integer),
                    new FieldDefinition('price', FieldType::Float),
                    new FieldDefinition('active', FieldType::Boolean),
                    new FieldDefinition('published_at', FieldType::DateTime),
                    new FieldDefinition('config', FieldType::Json),
                ],
            ],
        ]);

        $generator = new MigrationGenerator(new Filesystem(), $registry, $this->outputDir);
        $written = $generator->generate();

        $content = \file_get_contents($written[0]);
        self::assertIsString($content);
        self::assertStringContainsString("\$table->string('name')", $content);
        self::assertStringContainsString("\$table->integer('count')", $content);
        self::assertStringContainsString("\$table->float('price')", $content);
        self::assertStringContainsString("\$table->boolean('active')", $content);
        self::assertStringContainsString("\$table->dateTime('published_at')", $content);
        self::assertStringContainsString("\$table->json('config')", $content);
    }

    public function testGeneratesForeignKeyFromBelongsToRelation(): void
    {
        $registry = $this->buildRegistry([
            'product' => [
                'label'  => 'Product',
                'fields' => [
                    new FieldDefinition('name', FieldType::String),
                    new FieldDefinition('category_id', FieldType::Integer, nullable: false),
                ],
                'relations' => [
                    new RelationDefinition('category', RelationType::BelongsTo, 'category', foreignKey: 'category_id'),
                ],
            ],
        ]);

        $generator = new MigrationGenerator(new Filesystem(), $registry, $this->outputDir);
        $written = $generator->generate();

        $content = \file_get_contents($written[0]);
        self::assertIsString($content);

        // FK column should be unsignedBigInteger, not integer
        self::assertStringContainsString("\$table->unsignedBigInteger('category_id')", $content);
        self::assertStringNotContainsString("\$table->integer('category_id')", $content);

        // Foreign key constraint
        self::assertStringContainsString("->foreign('category_id')->references('id')->on('categories')", $content);
    }

    public function testForeignKeyInfersForeignColumnFromRelationName(): void
    {
        $registry = $this->buildRegistry([
            'order' => [
                'label'  => 'Order',
                'fields' => [
                    new FieldDefinition('user_id', FieldType::Integer),
                ],
                'relations' => [
                    // No explicit foreignKey — should infer 'user_id' from relation name 'user'
                    new RelationDefinition('user', RelationType::BelongsTo, 'user'),
                ],
            ],
        ]);

        $generator = new MigrationGenerator(new Filesystem(), $registry, $this->outputDir);
        $written = $generator->generate();

        $content = \file_get_contents($written[0]);
        self::assertIsString($content);
        self::assertStringContainsString("\$table->unsignedBigInteger('user_id')", $content);
        self::assertStringContainsString("->foreign('user_id')->references('id')->on('users')", $content);
    }

    public function testFieldModifiers(): void
    {
        $registry = $this->buildRegistry([
            'setting' => [
                'label'  => 'Setting',
                'fields' => [
                    new FieldDefinition('key', FieldType::String, nullable: false, unique: true),
                    new FieldDefinition('value', FieldType::String, nullable: true, default: 'none', comment: 'The setting value'),
                    new FieldDefinition('group', FieldType::String, indexed: true),
                ],
            ],
        ]);

        $generator = new MigrationGenerator(new Filesystem(), $registry, $this->outputDir);
        $written = $generator->generate();

        $content = \file_get_contents($written[0]);
        self::assertIsString($content);

        self::assertStringContainsString('->unique()', $content);
        self::assertStringContainsString("->default('none')", $content);
        self::assertStringContainsString("->comment('The setting value')", $content);
        self::assertStringContainsString('->index()', $content);
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

        $generator = new MigrationGenerator(new Filesystem(), $registry, $this->outputDir);
        $written = $generator->generate(only: ['product']);

        self::assertCount(1, $written);
        $content = \file_get_contents($written[0]);
        self::assertIsString($content);
        self::assertStringContainsString("'products'", $content);
        self::assertStringNotContainsString("'categories'", $content);
    }

    public function testEmptyRegistryGeneratesNothing(): void
    {
        $registry = $this->buildRegistry([]);
        $generator = new MigrationGenerator(new Filesystem(), $registry, $this->outputDir);
        $written = $generator->generate();

        self::assertSame([], $written);
    }

    /**
     * @param array<string, array<string, mixed>> $config
     */
    private function buildRegistry(array $config): Registry
    {
        return new Registry(new ArrayRegistryDriver($config));
    }
}
