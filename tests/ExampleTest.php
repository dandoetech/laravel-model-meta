<?php

declare(strict_types=1);

namespace DanDoeTech\PackageSkeleton\Tests;

use DanDoeTech\PackageSkeleton\Example;
use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function testGreet(): void
    {
        $ex = new Example();
        $this->assertSame('Hello, World!', $ex->greet('World'));
    }
}
