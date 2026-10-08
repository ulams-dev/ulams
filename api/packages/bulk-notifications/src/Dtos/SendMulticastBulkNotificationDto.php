<?php

namespace EscolaLms\BulkNotifications\Dtos;

use Illuminate\Http\Request;

class SendMulticastBulkNotificationDto extends SendBulkNotificationDto
{
    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('channel'),
            $request->input('sections'),
        );
    }
}
