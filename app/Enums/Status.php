<?php

namespace App\Enums;

enum Status
{
    case OPEN;
    case IN_PROGRESS;
    case CLOSED;

    public function toIcon(): string
    {
        return match ($this) {
            self::OPEN => "lock-keyhole-open",
            self::IN_PROGRESS => "circle-ellipsis",
            self::CLOSED => "lock-keyhole",
        };
    }
}
