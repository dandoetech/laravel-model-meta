<?php

declare(strict_types=1);

use DanDoeTech\LaravelModelMeta\ModelMeta\ArrayModelMetaProvider;
use DanDoeTech\LaravelModelMeta\ModelMeta\IntrospectingModelMetaProvider;

return [

    /**
     * Resource → Model map used by the introspector.
     * Example:
     *   'product'  => App\Models\Product::class,
     *   'category' => App\Models\Category::class,
     */
    'resource_to_model' => [
        // 'product'  => App\Models\Product::class,
        // 'category' => App\Models\Category::class,
    ],

    /**
     * Array provider seed to override or enrich metadata.
     * Use this to add virtual fields, or to define fields for resources
     * that cannot be introspected.
     */
    'array_fields' => [
        // 'product' => [
        //     new \DanDoeTech\ResourceRegistry\Definition\FieldDefinition(
        //         'virtual_flag', \DanDoeTech\ResourceRegistry\Definition\FieldType::Boolean, false
        //     ),
        // ],
    ],

    /**
     * Build order of the composite provider.
     * The first provider that returns a non-empty field list wins.
     */
    'provider_order' => [
        ArrayModelMetaProvider::class,
        IntrospectingModelMetaProvider::class,
    ],
];
