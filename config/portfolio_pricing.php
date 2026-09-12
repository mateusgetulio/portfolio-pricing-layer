<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pace Curve
    |--------------------------------------------------------------------------
    |
    | Synthetic configuration for this prototype, not real booking data. Each
    | point is the share of a group that should already be booked at a given
    | lead time. Targets between points are interpolated linearly. Production
    | would fit these values from historical booking curves per market.
    |
    */

    'pace_curve' => [
        ['lead_time_days' => 1, 'weekday' => 0.95, 'weekend' => 0.97],
        ['lead_time_days' => 3, 'weekday' => 0.90, 'weekend' => 0.95],
        ['lead_time_days' => 7, 'weekday' => 0.80, 'weekend' => 0.85],
        ['lead_time_days' => 14, 'weekday' => 0.65, 'weekend' => 0.70],
        ['lead_time_days' => 30, 'weekday' => 0.45, 'weekend' => 0.50],
        ['lead_time_days' => 60, 'weekday' => 0.30, 'weekend' => 0.35],
    ],

    /*
    |--------------------------------------------------------------------------
    | Group Modes And Leader Budget
    |--------------------------------------------------------------------------
    |
    | Margins are shares of the group measured against the pace target. The
    | leader budget rounds up by default, which leans one price leader toward
    | filling when the gap to the target is fractional.
    |
    */

    'min_group_size' => 3,
    'protect_margin' => 0.05,
    'fill_margin' => 0.05,
    'budget_rounding' => 'ceil',

    /*
    |--------------------------------------------------------------------------
    | Discounts
    |--------------------------------------------------------------------------
    |
    | All discounts are fractions of each unit's own base price, so the layer
    | never overrides how the per-unit engine values one unit against another.
    |
    */

    'urgency_window_days' => 14,
    'base_step' => 0.02,
    'shortfall_weight' => 0.25,
    'urgency_weight' => 0.05,
    'max_discount' => 0.15,
    'leader_taper' => 0.25,

    /*
    |--------------------------------------------------------------------------
    | Booking Strength
    |--------------------------------------------------------------------------
    |
    | Units with fewer nights of history than this count as average for their
    | group, so a new listing is never discounted first for lack of data.
    |
    */

    'min_history_nights' => 30,

];
