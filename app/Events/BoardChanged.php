<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

// Public invalidation contains no tournament, invitation or participant data.
class BoardChanged implements ShouldBroadcastNow
{
    public function broadcastOn(): array
    {
        return [new Channel('tv-board')];
    }

    public function broadcastAs(): string
    {
        return 'board.changed';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
