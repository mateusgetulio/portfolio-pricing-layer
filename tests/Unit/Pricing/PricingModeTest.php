<?php

use App\Pricing\Enums\PricingMode;

it('labels every mode for people', function (PricingMode $mode, string $label) {
    expect($mode->label())->toBe($label);
})->with([
    [PricingMode::Fill, 'Behind pace'],
    [PricingMode::Hold, 'On pace'],
    [PricingMode::Protect, 'Ahead of pace'],
    [PricingMode::PassThrough, 'Too small for portfolio pricing'],
]);
