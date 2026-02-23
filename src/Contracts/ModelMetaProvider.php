<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Contracts;

use DanDoeTech\LaravelModelMeta\DTO\ModelMeta;

interface ModelMetaProvider
{
    /**
     * @return iterable<ModelMeta>
     */
    public function all(): iterable;

    public function get(string $name): ?ModelMeta;
}
