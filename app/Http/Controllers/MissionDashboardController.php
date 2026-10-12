<?php

namespace App\Http\Controllers;

use App\Services\MissionStateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MissionDashboardController extends Controller
{
    public function __invoke(Request $request, MissionStateService $service): View
    {
        $input = $request->validate(['scenario' => ['sometimes', 'string', Rule::in(array_keys(config('mission.fixtures')))]]);
        $scenario = $input['scenario'] ?? config('mission.default_scenario');

        return view('mission-dashboard', ['state' => $service->get($scenario), 'scenario' => $scenario]);
    }
}
