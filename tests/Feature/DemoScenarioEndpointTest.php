<?php

it('prices the worked example as JSON', function () {
    $this->getJson('/api/demo/scenario?booked=8&lead_time=8&day_type=weekend')
        ->assertOk()
        ->assertJsonPath('data.night', '2026-09-19')
        ->assertJsonPath('data.assessment.mode', 'fill')
        ->assertJsonPath('data.assessment.mode_label', 'Behind pace')
        ->assertJsonPath('data.assessment.target_booked', 10)
        ->assertJsonPath('data.assessment.leaders', 2)
        ->assertJsonPath('data.answers.where', '8 of 12 booked, target 10 by 8 days out.')
        ->assertJsonPath('data.answers.what', '2 price leaders, 2 units held.')
        ->assertJsonPath('data.answers.why', 'They have the weakest recent booking strength in the group.')
        ->assertJsonPath('data.comparison.without_layer', '4 available units keep their independent prices.')
        ->assertJsonPath('data.comparison.with_layer', '2 become price leaders at 8% and 6%, 2 held.')
        ->assertJsonPath('data.units.11.id', 'unit-12')
        ->assertJsonPath('data.units.11.recommended_price_cents', 12800)
        ->assertJsonPath('data.units.11.reason', '-8%: 8 of 12 booked, target 10 by 8 days out, leader budget 2, weakest recent occupancy in the group.');
});

it('returns every field the demo screen reads', function () {
    $this->getJson('/api/demo/scenario?booked=8&lead_time=8&day_type=weekend')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'night',
            'day_type',
            'lead_time_days',
            'assessment' => ['booked', 'available', 'sellable', 'occupancy', 'target', 'target_booked', 'mode', 'mode_label', 'leader_budget', 'discount_rate', 'leaders'],
            'answers' => ['where', 'what', 'why'],
            'comparison' => ['without_layer', 'with_layer'],
            'units' => ['*' => ['id', 'name', 'status', 'booking_strength', 'base_price_cents', 'recommended_price_cents', 'discount', 'rule', 'leader_rank', 'reason']],
        ]]);
});

it('walks through fill, hold and protect as the slider moves', function (int $booked, string $mode, int $leaders) {
    $this->getJson("/api/demo/scenario?booked={$booked}&lead_time=8&day_type=weekend")
        ->assertOk()
        ->assertJsonPath('data.assessment.mode', $mode)
        ->assertJsonPath('data.assessment.leaders', $leaders);
})->with([
    '3 of 12 booked' => [3, 'fill', 7],
    '8 of 12 booked' => [8, 'fill', 2],
    '10 of 12 booked' => [10, 'hold', 0],
    '11 of 12 booked' => [11, 'protect', 0],
]);

it('answers the three product questions at the edges of the sliders', function (int $booked, int $leadTime, string $what, string $why, string $withoutLayer, string $withLayer) {
    $this->getJson("/api/demo/scenario?booked={$booked}&lead_time={$leadTime}&day_type=weekend")
        ->assertOk()
        ->assertJsonPath('data.answers.what', $what)
        ->assertJsonPath('data.answers.why', $why)
        ->assertJsonPath('data.comparison.without_layer', $withoutLayer)
        ->assertJsonPath('data.comparison.with_layer', $withLayer);
})->with([
    'on pace' => [10, 8, 'No discounts, 2 units held.', 'The group is on pace, so no unit needs a discount.', '2 available units keep their independent prices.', 'No unit is discounted.'],
    'ahead of pace' => [11, 8, 'No discounts, 1 unit protected.', 'The group is ahead of pace, so every price is protected.', '1 available unit keeps its independent price.', 'No unit is discounted.'],
    'fully booked' => [12, 8, 'No discounts, every sellable unit is booked.', 'No unit is available, so there is nothing to reprice.', 'No unit is available, so nothing changes.', 'No unit is discounted.'],
    'every unit leads' => [0, 0, '12 price leaders.', 'The leader budget covers every available unit, so all of them lead.', '12 available units keep their independent prices.', '12 become price leaders at 14%, 11%, 8%, 6%, 5%, 3%, 3%, 1%, 1%, 1%, 1% and 1%.'],
]);

it('uses the weekday night for weekday scenarios', function () {
    $this->getJson('/api/demo/scenario?booked=8&lead_time=8&day_type=weekday')
        ->assertOk()
        ->assertJsonPath('data.night', '2026-09-20')
        ->assertJsonPath('data.day_type', 'weekday')
        ->assertJsonPath('data.units.11.base_price_cents', 11700);
});

it('does not start a session for slider requests', function () {
    $this->getJson('/api/demo/scenario?booked=8&lead_time=8&day_type=weekend')
        ->assertOk()
        ->assertCookieMissing(config('session.cookie'))
        ->assertCookieMissing('XSRF-TOKEN');
});

it('rejects slider values outside their ranges', function (string $query, string $field) {
    $this->getJson("/api/demo/scenario?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'too many booked units' => ['booked=13&lead_time=8&day_type=weekend', 'booked'],
    'negative booked units' => ['booked=-1&lead_time=8&day_type=weekend', 'booked'],
    'lead time beyond the slider' => ['booked=8&lead_time=61&day_type=weekend', 'lead_time'],
    'unknown day type' => ['booked=8&lead_time=8&day_type=holiday', 'day_type'],
    'missing values' => ['', 'booked'],
]);

it('answers invalid requests with JSON errors without a JSON Accept header', function () {
    $this->get('/api/demo/scenario?booked=abc&lead_time=8&day_type=weekend')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('booked');
});
