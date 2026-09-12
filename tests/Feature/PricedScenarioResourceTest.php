<?php

use App\Http\Resources\PricedScenarioResource;

it('describes price leaders that the floor price kept from a full discount', function () {
    $snapshot = scenario([
        ['id' => 'unit-a', 'status' => 'available', 'occupancy' => 0.5],
        ['id' => 'unit-b', 'status' => 'available', 'occupancy' => 0.55, 'base' => 9000],
        ['id' => 'unit-c', 'status' => 'available', 'occupancy' => 0.6, 'floor' => 14000],
        ['id' => 'unit-d', 'status' => 'available', 'occupancy' => 0.65, 'base' => 14050, 'floor' => 14000],
        ['id' => 'unit-e', 'status' => 'booked', 'occupancy' => 0.9],
    ]);

    $scenario = (new PricedScenarioResource($snapshot, pricer()->price($snapshot)))->resolve();

    expect($scenario['answers']['what'])->toBe('4 price leaders.')
        ->and($scenario['comparison']['with_layer'])->toBe('4 become price leaders at 15%, the floor price, an unchanged price and less than 1%.');
});
