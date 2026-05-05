<?php

namespace App\Events;

use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaminaUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly float $stamina,
        public readonly CarbonImmutable $staminaLastUpdatedAt,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'stamina' => $this->stamina,
            'stamina_last_updated_at' => $this->staminaLastUpdatedAt->toIso8601String(),
        ];
    }
}
