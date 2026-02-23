<?php

declare(strict_types=1);

namespace DanDoeTech\LaravelModelMeta\Support;

final class ValueGetter
{
    /**
     * Try getters, then public props, then array shape.
     *
     * @param object|array<string,mixed> $source
     */
    public static function get(object|array $source, string $key, mixed $default = null): mixed
    {
        if (is_object($source)) {
            $candidates = [
                'get' . ucfirst($key),
                $key,
                'is' . ucfirst($key),
                'has' . ucfirst($key),
            ];

            foreach ($candidates as $method) {
                if (method_exists($source, $method)) {
                    /** @phpstan-ignore-next-line */
                    $val = $source->{$method}();
                    return $val ?? $default;
                }
            }

            if (property_exists($source, $key)) {
                /** @phpstan-ignore-next-line */
                return $source->{$key} ?? $default;
            }

            if (method_exists($source, 'toArray')) {
                /** @phpstan-ignore-next-line */
                $arr = $source->toArray();
                if (is_array($arr) && array_key_exists($key, $arr)) {
                    return $arr[$key];
                }
            }

            return $default;
        }

        // array
        return $source[$key] ?? $default;
    }
}
