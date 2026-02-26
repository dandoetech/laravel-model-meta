# Laravel Model Meta

Generate Eloquent Models and Migrations from Resource Registry definitions. Also provides model introspection for the OpenAPI generator.

## Installation

```bash
composer require dandoetech/laravel-model-meta
```

The service provider is auto-discovered. Publish the config:

```bash
php artisan vendor:publish --tag=model-meta-config
```

Requires [`dandoetech/laravel-resource-registry`](https://github.com/dandoetech/laravel-resource-registry).

## Quick Start

Given a resource definition:

```php
class ProductResource extends Resource implements HasEloquentModel
{
    public function model(): string { return \App\Models\Product::class; }

    protected function define(ResourceBuilder $b): void
    {
        $b->key('product')
          ->label('Product')
          ->timestamps()
          ->softDeletes()
          ->field('name', FieldType::String, nullable: false, rules: ['required', 'max:120'])
          ->field('price', FieldType::Float, nullable: false)
          ->field('category_id', FieldType::Integer, nullable: false)
          ->belongsTo('category', foreignKey: 'category_id')
          ->action('create')
          ->action('update');
    }
}
```

Generate the model and migration:

```bash
php artisan model-meta:generate-models
php artisan model-meta:generate-migrations
```

### Generated Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'products';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'price',
        'category_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'price' => 'float',
        'category_id' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class, 'category_id');
    }
}
```

### Generated Migration

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->float('price');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('category_id')->references('id')->on('categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

Generated models are `final`. Devs edit them directly — add mutators, scopes, custom methods as needed.

## Artisan Commands

### `model-meta:generate-models`

```bash
php artisan model-meta:generate-models                     # all resources
php artisan model-meta:generate-models --only=product      # specific resources
php artisan model-meta:generate-models --force             # overwrite existing
```

Generates one model per resource. Includes `$fillable`, `$casts`, relation methods, `HasFactory`, and `SoftDeletes` (when enabled). Runs `composer dump-autoload` after generation.

### `model-meta:generate-migrations`

```bash
php artisan model-meta:generate-migrations                 # all resources
php artisan model-meta:generate-migrations --only=product  # specific resources
php artisan model-meta:generate-migrations --force         # overwrite existing
```

Generates timestamped migration files. Maps field types, applies nullable/unique/index/default modifiers, and creates foreign key constraints for BelongsTo relations.

### Type Mapping

| FieldType | Model cast | Migration column |
|---|---|---|
| `String` | *(none)* | `$table->string()` |
| `Integer` | `'integer'` | `$table->integer()` |
| `Float` | `'float'` | `$table->float()` |
| `Boolean` | `'boolean'` | `$table->boolean()` |
| `DateTime` | `'datetime'` | `$table->dateTime()` |
| `Json` | `'array'` | `$table->json()` |

## Configuration

`config/model_meta.php`:

```php
return [
    // Map resource keys to Eloquent models (for introspection)
    'resource_to_model' => [
        // 'product' => App\Models\Product::class,
    ],

    // Extra fields not discoverable by introspection
    'array_fields' => [
        // 'product' => [new FieldDefinition('virtual_flag', FieldType::Boolean, false)],
    ],

    // Provider priority (first non-empty result wins)
    'provider_order' => [
        ArrayModelMetaProvider::class,
        IntrospectingModelMetaProvider::class,
    ],
];
```

## Model Introspection

The `IntrospectingModelMetaProvider` reads field metadata from existing Eloquent models (via schema or casts). This is used by the OpenAPI generator as a fallback when resources don't have explicit field definitions.

## Testing

```bash
composer install
composer test        # PHPUnit (Orchestra Testbench)
composer qa          # cs:check + phpstan + test
```

## License

MIT
