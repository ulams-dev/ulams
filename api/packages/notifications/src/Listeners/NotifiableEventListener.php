<?php

namespace EscolaLms\Notifications\Listeners;

use EscolaLms\Core\Models\User;
use EscolaLms\Notifications\Core\EventNotification;
use ReflectionClass;

class NotifiableEventListener
{
    public function handle($event)
    {
        $data = $this->getDataFromEvent($event);
        $userKey = $this->getUserKey($data);
        if ($userKey) {
            /** @var User $user */
            $user = $data[$userKey];
            unset($data[$userKey]);

            if ($user->getKey()) {
                $user->notify(new EventNotification($event, $data));
            }
        }
    }

    protected function getDataFromEvent(object $event): array
    {
        if (method_exists($event, 'toArray')) {
            return $event->toArray();
        }
        return $this->extractPropertiesFromEvent($event);
    }

    protected function extractPropertiesFromEvent($event): array
    {
        $reflectionClass = new ReflectionClass($event);
        $properties = $reflectionClass->getProperties();

        if ($parentClass = $reflectionClass->getParentClass()) {
            $properties = array_merge($properties, $parentClass->getProperties());
        }

        $values = [];

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);

            if (!$property->isInitialized($event)) {
                continue;
            }

            $name = $property->getName();
            $value = $property->getValue($event);
            $values[$name] = $value;
        }

        return $values;
    }

    protected function getUserKey(array $data): ?string
    {
        if (array_key_exists('user', $data) && $data['user'] instanceof User) {
            return 'user';
        }
        foreach ($data as $key => $value) {
            if ($value instanceof User) {
                return $key;
            }
        }
        return null;
    }
}
