<?php

namespace App\Http\Requests;

use App\Pricing\DemoScenario;
use App\Pricing\Enums\DayType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScenarioRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(DemoScenario $demo): array
    {
        return [
            'booked' => ['required', 'integer', 'between:0,'.$demo->unitCount()],
            'lead_time' => ['required', 'integer', 'between:0,'.DemoScenario::MAX_LEAD_TIME_DAYS],
            'day_type' => ['required', Rule::enum(DayType::class)],
        ];
    }
}
