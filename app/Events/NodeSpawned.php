<?php

namespace App\Events;

use App\Models\MiningNode;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NodeSpawned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly MiningNode $node,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('island.'.$this->node->island_id.'.nodes'),
        ];
    }

    public function broadcastWith(): array
    {
        $type = $this->node->nodeType;

        return [
            'node' => [
                'id'           => $this->node->id,
                'max_hp'       => $this->node->max_hp,
                'current_hp'   => $this->node->current_hp,
                'is_respawning' => false,
                'respawns_at'  => null,
                'node_type'    => [
                    'slug' => $type->slug,
                    'name' => $type->name,
                    'tier' => $type->tier,
                ],
            ],
        ];
    }
}
