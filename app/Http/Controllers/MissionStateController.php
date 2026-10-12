<?php

namespace App\Http\Controllers;

use App\Services\MissionStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MissionStateController extends Controller
{
    public function __invoke(Request $request, MissionStateService $service): JsonResponse
    {
        $input = $request->validate(['scenario' => ['sometimes', 'string', Rule::in(array_keys(config('mission.fixtures')))]]);

        return response()->json($service->get($input['scenario'] ?? config('mission.default_scenario')))
            ->header('Cache-Control', 'no-store');
    }
}
