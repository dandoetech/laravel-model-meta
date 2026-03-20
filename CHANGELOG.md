# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-03-20

### Added
- Model and migration generation support for new FieldTypes: `Date`, `Text`, `Email`, `Url`, `Enum`

## [0.1.0] - 2026-03-15

### Changed
- Config file renamed from `model_meta.php` to `ddt_model_meta.php` (consistent `ddt_` naming convention)

### Added
- `model-meta:generate-models` artisan command with `--only` and `--force` options
- `model-meta:generate-migrations` artisan command with `--only` and `--force` options
- `ModelGenerator` producing final Eloquent models with fillable, casts, relation methods, HasFactory, and SoftDeletes
- `MigrationGenerator` producing timestamped migrations with field columns, nullable/unique/index/default modifiers, and foreign key constraints
- `IntrospectingModelMetaProvider` reading field metadata from existing Eloquent models via schema or casts
- `LaravelTypeMapper` mapping Laravel column names and cast types to `FieldType` enum
- `ModelMetaServiceProvider` with composite provider pattern and deferred binding
- `ddt_model_meta.php` config with `resource_to_model`, `array_fields`, and `provider_order` options
