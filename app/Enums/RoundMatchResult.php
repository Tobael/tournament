<?php

namespace App;

enum RoundMatchResult: string
{
    case TWOZERO = '2-0';
    case TWOONE = '2-1';
    case ONETWO = '1-2';
    case ZEROTWO = '0-2';
    case DRAW = '0-0';
}
