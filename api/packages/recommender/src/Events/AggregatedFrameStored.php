<?php

namespace EscolaLms\Recommender\Events;

use EscolaLms\Recommender\Models\AggregatedFrame;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AggregatedFrameStored implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        private AggregatedFrame $aggregatedFrame
    ) {}

    public function getAggregatedFrame(): AggregatedFrame
    {
        return $this->aggregatedFrame;
    }

    public function broadcastQueue(): string
    {
        return 'broadcast';
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("{$this->aggregatedFrame->model_type}.{$this->aggregatedFrame->model_id}.{$this->aggregatedFrame->term->timestamp}");
    }

    public function broadcastWith(): array
    {
        return [
            'should_break' => $this->aggregatedFrame->should_break ?? false,
            'break_confidence' => $this->aggregatedFrame->break_confidence,
            'recommended_in_minutes' => $this->aggregatedFrame->recommended_in_minutes,
            'window_start' => $this->aggregatedFrame->window_start,
            'window_end' => $this->aggregatedFrame->window_end,
            'attention' => $this->aggregatedFrame->avg_attention,
            'emotion' => $this->aggregatedFrame->max_emotion,
            'emotion_percentage' => $this->aggregatedFrame->max_emotion_value,
            'meet_users_count' => $this->aggregatedFrame->meet_users_count,
        ];
    }

    public function broadcastAs(): string
    {
        return 'AggregatedFrameStored';
    }
}
