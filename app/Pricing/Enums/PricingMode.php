<?php

namespace App\Pricing\Enums;

enum PricingMode: string
{
    case PassThrough = 'pass_through';
    case Fill = 'fill';
    case Hold = 'hold';
    case Protect = 'protect';

    public function label(): string
    {
        return match ($this) {
            self::PassThrough => 'Too small for portfolio pricing',
            self::Fill => 'Behind pace',
            self::Hold => 'On pace',
            self::Protect => 'Ahead of pace',
        };
    }
}
