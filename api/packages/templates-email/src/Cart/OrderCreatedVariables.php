<?php

namespace EscolaLms\TemplatesEmail\Cart;

use EscolaLms\Cart\Models\Order;
use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Events\EventWrapper;

class OrderCreatedVariables extends CartVariables
{
    const VAR_USER_NAME           = '@VarUserName';

    public static function mockedVariables(?User $user = null): array
    {
        $faker = \Faker\Factory::create();
        return array_merge(parent::mockedVariables(), [
            self::VAR_USER_NAME       => $faker->name(),
        ]);
    }

    public static function variablesFromEvent(EventWrapper $event): array
    {
        return array_merge(parent::variablesFromEvent($event), [
            self::VAR_USER_NAME    => $event->getUser()->name
        ]);
    }

    public static function assignableClass(): ?string
    {
        return Order::class;
    }

    public static function requiredVariables(): array
    {
        return [
            self::VAR_USER_NAME,
        ];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        return [];
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Thank you for your order'),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Thanks for your order.</p>', [
                'user_name' => self::VAR_USER_NAME
            ])),
        ];
    }
}
