<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NodeUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $nodeId,
        public readonly int $currentHp,
        public readonly int $islandId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('island.'.$this->islandId.'.nodes'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'node_id' => $this->nodeId,
            'current_hp' => $this->currentHp,
        ];
    }
}
