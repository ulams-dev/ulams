<?php

use Ulams\Consultations\Broadcasting\ConsultationChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('consultation.{consultation}.{term}', ConsultationChannel::class, ['middleware' => 'auth:api']);
