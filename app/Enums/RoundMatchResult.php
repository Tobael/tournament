<?php

namespace App\Enums;

enum RoundMatchResult: string
{
    case TWOZERO = '2-0';
    case TWOONE = '2-1';
    case ONETWO = '1-2';
    case ZEROTWO = '0-2';
    case DRAW = '0-0';

    public function toPoints(): array
    {
        return match ($this) {
            self::TWOZERO => [2, 0],
            self::TWOONE => [2, 1],
            self::ZEROTWO => [0, 2],
            self::ONETWO => [1, 2],
            self::DRAW => [1, 1],
        };
    }
}
