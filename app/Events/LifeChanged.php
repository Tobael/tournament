<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class LifeChanged implements ShouldBroadcastNow
{
    public function __construct(private string $invite, public array $state) {}

    public function broadcastOn(): array
    {
        return [new Channel('tournament.'.$this->invite)];
    }

    public function broadcastAs(): string
    {
        return 'life.changed';
    }
}
