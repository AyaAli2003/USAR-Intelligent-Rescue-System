<?php

namespace App\Services;

use App\Contracts\MissionStateSource;
use App\Exceptions\MissionStateUnavailable;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

class MissionStateService
{
    public function __construct(private MissionStateSource $source, private MissionStateValidator $validator) {}

    /** @return array<string, mixed> */
    public function get(string $scenario): array
    {
        try {
            return $this->validator->validate($this->source->load($scenario));
        } catch (ValidationException|JsonException|RuntimeException $exception) {
            throw new MissionStateUnavailable('Operational mission data is unavailable or invalid.', previous: $exception);
        }
    }
}
