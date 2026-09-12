<?php

namespace App\Pricing\Enums;

enum PricingRule: string
{
    case BookedNight = 'booked_night';
    case BlockedNight = 'blocked_night';
    case SmallGroup = 'small_group';
    case AheadOfPace = 'ahead_of_pace';
    case OnPace = 'on_pace';
    case HeldForWeakerUnits = 'held_for_weaker_units';
    case PriceLeader = 'price_leader';
}
