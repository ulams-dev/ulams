<?php

namespace EscolaLms\TemplatesEmail\Cart;

use EscolaLms\Cart\Models\Product;
use EscolaLms\Cart\Models\ProductProductable;
use EscolaLms\Cart\Services\Contracts\ProductServiceContract;
use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;

class ProductAttachedVariables extends CartVariables
{
    const VAR_USER_NAME    = '@VarUserName';
    const VAR_PRODUCT_NAME = '@VarProductName';
    const VAR_PRODUCTABLES = '@VarProductables';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();

        return array_merge(parent::mockedVariables(), [
            self::VAR_USER_NAME     => $faker->name(),
            self::VAR_PRODUCT_NAME  => $faker->word(),
            self::VAR_PRODUCTABLES  => $faker->word(),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        $productables = $event->getProduct()->productables
            ->map(fn (ProductProductable $productProductable) => app(ProductServiceContract::class)
            ->mapProductProductableToJsonResource($productProductable)
            ->toArray($event->getProduct()));

        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME    => $event->getUser()->name,
            self::VAR_PRODUCT_NAME => $event->getProduct()->name,
            self::VAR_PRODUCTABLES => view('templates-email::productables', ['productables' => $productables])->render(),
        ]);
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('You have been assigned to :product_name', [
                'product_name' => self::VAR_PRODUCT_NAME
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>You have been assigned to product ":product_name". Please see details below.</p> :productables', [
                'user_name' => self::VAR_USER_NAME,
                'product_name' => self::VAR_PRODUCT_NAME,
                'productables' => self::VAR_PRODUCTABLES,
            ])),
        ];
    }

    public static function requiredVariables(): array
    {
        return [
            self::VAR_USER_NAME,
            self::VAR_PRODUCT_NAME,
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        if ($sectionKey === 'content') {
            [
                self::VAR_USER_NAME,
                self::VAR_PRODUCT_NAME,
            ];
        }

        return [];
    }

    public static function assignableClass(): ?string
    {
        return Product::class;
    }
}
