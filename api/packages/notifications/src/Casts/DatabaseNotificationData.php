<?php

namespace EscolaLms\Notifications\Casts;

use EscolaLms\Notifications\Models\DatabaseNotification;
use Exception;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;

class DatabaseNotificationData implements CastsAttributes
{
    use SerializesAndRestoresModelIdentifiers;

    public function get($model, $key, $value, $attributes)
    {
        assert($model instanceof DatabaseNotification);
        assert(is_string($value));

        $data = json_decode($value, true);

        foreach ($data as $key => $attribute) {
            try {
                $unserialized = unserialize($attribute);
            } catch (\Throwable $th) {
                $unserialized = $attribute;
            }

            /** 
             * If we have notifications that contain references to Models that no longer exist in database 
             * (or Model classes that no longer exist in codebase), 
             * this will return null instead of throwing errors.
             * */
            if ($unserialized instanceof ModelIdentifier) {
                if ($unserialized->class && class_exists($unserialized->class)) {
                    try {
                        $data[$key] = $this->getRestoredPropertyValue($unserialized);
                    } catch (\Throwable $th) {
                        $data[$key] = null;
                    }
                } else {
                    $data[$key] = null;
                }
            }
        }

        return $data;
    }

    public function set($model, $key, $value, $attributes)
    {
        assert($model instanceof DatabaseNotification);
        assert(is_array($value));

        $value = array_map(fn ($attribute) => is_object($attribute) ? serialize($this->getSerializedPropertyValue($attribute)) : $attribute, $value);

        return json_encode($value);
    }
}
