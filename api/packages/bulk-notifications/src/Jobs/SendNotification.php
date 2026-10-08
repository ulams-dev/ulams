<?php

namespace EscolaLms\BulkNotifications\Jobs;

use EscolaLms\BulkNotifications\Channels\NotificationChannel;
use EscolaLms\BulkNotifications\Events\NotificationSent;
use EscolaLms\BulkNotifications\ValueObjects\Notification;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Notification $notification,
        private string $channel
    )
    {
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getChannelInstance(): NotificationChannel
    {
        return app($this->getChannel());
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        try {
            $this->getChannelInstance()->send($this->getNotification());
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            throw $exception;
        }
    }
}
