<?php

namespace App\Services;

use App\Contracts\MissionStateSource;
use RuntimeException;

class FixtureMissionStateSource implements MissionStateSource
{
    public function load(string $scenario): array
    {
        $filename = config('mission.fixtures.'.$scenario);
        if (! is_string($filename)) {
            throw new RuntimeException('Unknown mission fixture.');
        }
        $path = resource_path('fixtures/'.$filename);
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Mission fixture is unavailable.');
        }
        $state = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($state)) {
            throw new RuntimeException('Mission fixture must be an object.');
        }

        return $state;
    }
}
