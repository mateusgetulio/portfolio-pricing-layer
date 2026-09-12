<?php

namespace App\Pricing\Enums;

enum NightStatus: string
{
    case Booked = 'booked';
    case Available = 'available';
    case Blocked = 'blocked';
}
