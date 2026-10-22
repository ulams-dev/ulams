<?php

namespace Ulams\Templates\Events;

use Ulams\Core\Models\User;
use Ulams\Templates\Support\TemplateLocale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use ReflectionClass;

/**
 * Event should have methods declared for retrieving its data,
 * for example Course related event should have getCourse() method,
 * but if it does not, we can try to extract 'course' property of the object
 * (either from toArray method or by using Reflection)
 *
 * This wrapper exists for enabling extraction of any available data from event instance.
 */
class EventWrapper
{
    use ForwardsCalls;

    private object $event;

    public function __construct(object $event)
    {
        $this->event = $event;
    }

    public function eventClass(): string
    {
        return get_class($this->event);
    }

    public function user(): ?User
    {
        try {
            $result = $this->__call('getUser', []);
            if ($result instanceof User) {
                return $result;
            }
        } catch (\BadMethodCallException $ex) {
            // ignore
        }

        $id = $this->extractIdForPropertyOfClass(User::class);
        return $id ? User::find($id) : null;
    }

    /**
     * The language of the event: its own `getLocale()` / `getLanguage()`, else the `language` (or `locale`) of the
     * first model it carries (a course has one). Null when there is none; the caller falls back.
     */
    public function language(): ?string
    {
        foreach (['getLocale', 'getLanguage'] as $getter) {
            try {
                $value = $this->__call($getter, []);
                if (is_string($value) && TemplateLocale::normalize($value)) {
                    return TemplateLocale::normalize($value);
                }
            } catch (\BadMethodCallException $ex) {
                // ignore
            }
        }

        foreach ($this->modelsOfTheEvent() as $value) {
            $attributes = $value->getAttributes();
            foreach (['language', 'locale'] as $key) {
                $locale = isset($attributes[$key]) && is_string($attributes[$key]) ? TemplateLocale::normalize($attributes[$key]) : null;
                if ($locale) {
                    return $locale;
                }
            }
        }

        return null;
    }

    /**
     * The models the event carries: the ones in its array form or properties, and the ones its getters
     * (`getCourse(): Course`) return. A getter is needed when the property is private to a parent class.
     *
     * @return Model[]
     */
    private function modelsOfTheEvent(): array
    {
        $values = method_exists($this->event, 'toArray') ? $this->event->toArray() : [];
        $values = array_merge(array_values($values), array_values($this->extractPropertiesFromBaseEvent()));

        foreach ((new ReflectionClass($this->event))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();
            if (
                !str_starts_with($method->getName(), 'get') || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0
                || !$type instanceof \ReflectionNamedType || $type->isBuiltin() || !is_a($type->getName(), Model::class, true)
            ) {
                continue;
            }
            try {
                $values[] = $method->invoke($this->event);
            } catch (\Throwable $e) {
                // a getter that cannot answer (an uninitialised property): the event has no language through it
            }
        }

        return array_values(array_filter($values, fn ($value) => $value instanceof Model));
    }

    public function getEmail(): ?string
    {
        try {

            return $this->__call('getEmail', []);
        } catch (\BadMethodCallException $ex) {
            // ignore
        }

        return null;
    }

    public function extractIdForPropertyOfClass(string $class): ?int
    {
        assert(is_a($class, Model::class, true));
        try {
            $basename = class_basename($class);
            $result = $this->__call(Str::camel("get $basename"), []);
            if (is_a($result, $class, true)) {
                return $result->getKey();
            }
            if (is_numeric($result)) {
                return $result;
            }
        } catch (\BadMethodCallException $ex) {
            // ignore
        }
        if (method_exists($this->event, 'toArray')) {
            foreach ($this->event->toArray() as $key => $value) {
                if (is_a($value, $class, true)) {
                    return $value->getKey();
                }
            }
        }
        foreach ($this->extractPropertiesFromBaseEvent() as $key => $value) {
            if (is_a($value, $class, true)) {
                return $value->getKey();
            }
        }
        return null;
    }

    public function __call($name, $arguments)
    {
        if (Str::startsWith($name, 'get') && !method_exists($this->event, $name)) {
            $field = Str::lower(Str::after($name, 'get'));
            $array = $this->toArray();
            if (array_key_exists($field, $array)) {
                return $array[$field];
            }
        }

        return $this->forwardCallTo($this->event, $name, $arguments);
    }

    public function toArray(): array
    {
        if (method_exists($this->event, 'toArray')) {
            return $this->event->toArray();
        }
        return $this->extractPropertiesFromBaseEvent();
    }

    private function extractPropertiesFromBaseEvent(): array
    {
        $properties = (new ReflectionClass($this->event))->getProperties();

        $values = [];

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);

            if (!$property->isInitialized($this->event)) {
                continue;
            }

            $name = $property->getName();
            $value = $property->getValue($this->event);
            $values[$name] = $value;
        }

        return $values;
    }
}
