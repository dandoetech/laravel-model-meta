# DanDoeTech PackageSkeleton

> A modern PHP package skeleton with CI, QA and quality reporting — ready for Packagist.

![Build](https://github.com/dandoetech/package-skeleton/actions/workflows/tests.yml/badge.svg)
![Static Analysis](https://github.com/dandoetech/package-skeleton/actions/workflows/static-analysis.yml/badge.svg)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/b7ccf297da214db5a81604b88ae0e704)](https://app.codacy.com?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![Codacy Badge](https://app.codacy.com/project/badge/Coverage/b7ccf297da214db5a81604b88ae0e704)](https://app.codacy.com?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_coverage)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=dandoetech_package-skeleton&metric=alert_status&token=0f6e812685c39ef11dcfff8b33a14c0c529a6fe1)](https://sonarcloud.io/summary/new_code?id=dandoetech_package-skeleton)

## Install

```bash
composer require dandoetech/package-skeleton
```

## Usage

```php
<?php

declare(strict_types=1);

use DanDoeTech\PackageSkeleton\Example;

$ex = new Example();
echo $ex->greet('World'); // Hello, World!
```

## Development

```bash
composer install
composer qa         # runs cs:check, phpstan, tests
composer cs:fix     # auto-fix coding style
composer test       # run test suite
composer test:coverage
```

## Quality Gates

- PSR-12 via PHP-CS-Fixer (with strict types, imports, trailing commas)
- PHPStan level `max`
- PHPUnit 11 with coverage (Clover + HTML)
- GitHub Actions: tests (PHP 8.2 / 8.3 / 8.4 / 8.5), static analysis, cache
- **Codacy** coverage upload (needs `CODACY_PROJECT_TOKEN` secret)
- **SonarCloud** analysis (needs `SONAR_TOKEN` secret)

## Releasing

- Create a tag like `v0.1.0`
- Push to GitHub — Packagist auto-updates if hooked, or submit manually

## Rename This Skeleton

- Replace vendor & package in `composer.json` (`dandoetech/package-skeleton`)
- Replace namespace `DanDoeTech\PackageSkeleton\` in `/src` and `/tests`
- Search/replace badges in `README.md`
- Optional: adjust `LICENSE` owner
