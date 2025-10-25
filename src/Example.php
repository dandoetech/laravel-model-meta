<?php

declare(strict_types=1);

namespace DanDoeTech\PackageSkeleton;

final class Example
{
    public function greet(string $name): string
    {
        return \sprintf('Hello, %s!', $name);
    }
}
