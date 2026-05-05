<?php

namespace App\Events;

use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NodeDepleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $nodeId,
        public readonly CarbonImmutable $respawnsAt,
        public readonly string $nodeTypeSlug,
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
            'respawns_at' => $this->respawnsAt->toIso8601String(),
            'next_node_type_slug' => $this->nodeTypeSlug,
        ];
    }
}
