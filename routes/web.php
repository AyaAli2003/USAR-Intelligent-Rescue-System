<?php

use App\Http\Controllers\MissionDashboardController;
use App\Http\Controllers\MissionStateController;
use Illuminate\Support\Facades\Route;

Route::withoutMiddleware('web')->group(function (): void {
    Route::get('/', MissionDashboardController::class)->name('mission.dashboard');
    Route::get('/api/v1/mission-state', MissionStateController::class)->name('mission.state');
});
