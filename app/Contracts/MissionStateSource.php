<?php

namespace App\Contracts;

interface MissionStateSource
{
    /** @return array<string, mixed> */
    public function load(string $scenario): array;
}
