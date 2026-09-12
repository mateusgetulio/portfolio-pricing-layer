<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScenarioRequest;
use App\Http\Resources\PricedScenarioResource;
use App\Pricing\Data\PacePoint;
use App\Pricing\Data\PricingConfig;
use App\Pricing\DemoScenario;
use App\Pricing\Enums\DayType;
use App\Pricing\PortfolioPricer;
use Illuminate\Contracts\View\View;

class DemoController extends Controller
{
    public function show(DemoScenario $demo, PricingConfig $config): View
    {
        return view('demo', [
            'unitCount' => $demo->unitCount(),
            'maxLeadTimeDays' => DemoScenario::MAX_LEAD_TIME_DAYS,
            'paceCurve' => array_map(fn (PacePoint $point): array => [
                'lead_time_days' => $point->leadTimeDays,
                'weekday' => $point->weekdayTarget,
                'weekend' => $point->weekendTarget,
            ], $config->paceCurve->points),
        ]);
    }

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
