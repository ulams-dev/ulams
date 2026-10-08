<?php

namespace EscolaLms\BulkNotifications\Rules;

use EscolaLms\BulkNotifications\Channels\NotificationChannel;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RequiredSection implements Rule
{
    private Request $request;

    private Collection $requiredSections;

    public function __construct()
    {
        $this->request = request();
        $this->requiredSections = collect();
    }

    public function passes($attribute, $value): bool
    {
        $channelClass = $this->request->input('channel');

        if (!class_exists($channelClass) || !in_array(NotificationChannel::class, class_implements($channelClass))) {
            return false;
        }

        $this->requiredSections = $channelClass::requiredSections();
        $sections = collect($value)->keys();

        return !$this->requiredSections->diff($sections)->count();
    }

    public function message(): string
    {
        return __('The :attribute must must contain keys [' . $this->requiredSections->implode(', ') . '].');
    }
}
