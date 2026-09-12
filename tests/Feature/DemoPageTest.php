<?php

it('sends the home page to the demo', function () {
    $this->get('/')->assertRedirect('/demo');
});

it('renders the demo screen around three product questions', function () {
    $this->get('/demo')
        ->assertOk()
        ->assertSee('Portfolio Pricing Layer')
        ->assertSee('Where are we?')
        ->assertSee('What are we doing?')
        ->assertSee('Why these units?')
        ->assertSee('Without the portfolio layer')
        ->assertSee('With the portfolio layer')
        ->assertSee('data-endpoint="/api/demo/scenario"', false);
});

it('sizes the sliders from the demo scenario', function () {
    $this->get('/demo')
        ->assertOk()
        ->assertSee('id="booked" type="range" min="0" max="12"', false)
        ->assertSee('id="lead-time" type="range" min="0" max="60"', false)
        ->assertSee('data-max-lead-time="60"', false);
});

it('draws the synthetic pace curve from the pricing config', function () {
    $this->get('/demo')
        ->assertOk()
        ->assertSee('Synthetic configuration for this prototype, not real booking data')
        ->assertSee('data-pace-curve="'.e(json_encode([
            ['lead_time_days' => 1, 'weekday' => 0.95, 'weekend' => 0.97],
            ['lead_time_days' => 3, 'weekday' => 0.9, 'weekend' => 0.95],
            ['lead_time_days' => 7, 'weekday' => 0.8, 'weekend' => 0.85],
            ['lead_time_days' => 14, 'weekday' => 0.65, 'weekend' => 0.7],
            ['lead_time_days' => 30, 'weekday' => 0.45, 'weekend' => 0.5],
            ['lead_time_days' => 60, 'weekday' => 0.3, 'weekend' => 0.35],
        ])).'"', false);
});

it('loads Alpine from a pinned version with an integrity hash', function () {
    $this->get('/demo')
        ->assertOk()
        ->assertSee('alpinejs@3.17.2', false)
        ->assertSee('integrity="sha384-', false);
});
