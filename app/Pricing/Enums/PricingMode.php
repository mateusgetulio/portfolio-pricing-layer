<?php

namespace App\Pricing\Enums;

enum PricingMode: string
{
    case PassThrough = 'pass_through';
    case Fill = 'fill';
    case Hold = 'hold';
    case Protect = 'protect';
}
