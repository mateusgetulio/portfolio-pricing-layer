<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScenarioRequest;
use App\Http\Resources\PricedScenarioResource;
use App\Pricing\DemoScenario;
use App\Pricing\Enums\DayType;
use App\Pricing\PortfolioPricer;

class DemoController extends Controller
{
    public function scenario(ScenarioRequest $request, DemoScenario $demo, PortfolioPricer $pricer): PricedScenarioResource
    {
        $snapshot = $demo->build(
            $request->integer('booked'),
            $request->integer('lead_time'),
            DayType::from($request->string('day_type')->toString()),
        );

        return new PricedScenarioResource($snapshot, $pricer->price($snapshot));
    }
}
