<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Tests;

use DanDoeTech\LaravelModelMeta\ModelMeta\IntrospectingModelMetaProvider;
use PHPUnit\Framework\TestCase;

final class IntrospectingModelMetaProviderTest extends TestCase
{
    public function testNoMappingReturnsEmptyList(): void
    {
        $p = new IntrospectingModelMetaProvider([]);
        self::assertSame([], $p->fieldsFor('unknown'));
    }
}
