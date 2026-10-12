<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MissionStateValidator
{
    /**
     * Unknown fields are rejected at every level to keep evaluation data outside the operational boundary.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function validate(array $state): array
    {
        $unit = ['required', 'numeric:strict', 'between:0,1'];
        $rules = [
            'schemaVersion' => ['required', Rule::in(['1.0'])],
            'timestamp' => ['required', 'integer:strict', 'min:0'],
            'mission' => ['required', 'array:missionId,mode,status,elapsedSeconds,coverage'],
            'mission.missionId' => ['required', 'string', 'max:80'],
            'mission.mode' => ['required', Rule::in(['SIMULATION', 'HIL'])],
            'mission.status' => ['required', Rule::in(['READY', 'SEARCHING', 'PAUSED', 'COMPLETED', 'FAILED'])],
            'mission.elapsedSeconds' => ['required', 'integer:strict', 'min:0'],
            'mission.coverage' => $unit,
            'probe' => ['required', 'array:x,y,state'],
            'probe.x' => ['required', 'numeric:strict'],
            'probe.y' => ['required', 'numeric:strict'],
            'probe.state' => ['required', Rule::in(['IDLE', 'MOVING', 'SCANNING', 'STOPPED'])],
            'sensors' => ['required', 'array:radar,thermal,acoustic'],
            'fusion' => ['required', 'array:fusionScore,confidence'],
            'fusion.fusionScore' => $unit,
            'fusion.confidence' => $unit,
            'localization' => ['present', 'array', 'list'],
            'localization.*' => ['required', 'array:candidateId,x,y,confidence'],
            'localization.*.candidateId' => ['required', 'string', 'distinct'],
            'localization.*.x' => ['required', 'numeric:strict'],
            'localization.*.y' => ['required', 'numeric:strict'],
            'localization.*.confidence' => $unit,
            'victims' => ['present', 'array', 'list'],
            'victims.*' => ['required', 'array:victimTrackId,candidateId,status,estimatedPosition,confidence,vitality,priority'],
            'victims.*.victimTrackId' => ['required', 'string', 'distinct'],
            'victims.*.candidateId' => ['required', 'string', 'distinct'],
            'victims.*.status' => ['required', Rule::in(['TENTATIVE', 'CONFIRMED', 'LOST'])],
            'victims.*.estimatedPosition' => ['required', 'array:x,y'],
            'victims.*.estimatedPosition.x' => ['required', 'numeric:strict'],
            'victims.*.estimatedPosition.y' => ['required', 'numeric:strict'],
            'victims.*.confidence' => $unit,
            'victims.*.vitality' => $unit,
            'victims.*.priority' => ['required', Rule::in(['HIGH', 'MEDIUM', 'LOW', 'UNASSIGNED'])],
            'ai' => ['present', 'array', 'list'],
            'ai.*' => ['required', 'array:candidateId,classification,probability,uncertainty,decision,abstained,modelVersion,featureVersion,timestamp,explanation'],
            'ai.*.candidateId' => ['required', 'string', 'distinct'],
            'ai.*.classification' => ['present', 'nullable', Rule::in(['TRUE_TRACK', 'HARD_NEGATIVE'])],
            'ai.*.probability' => $unit,
            'ai.*.uncertainty' => $unit,
            'ai.*.decision' => ['required', Rule::in(['ACCEPT_TRUE_TRACK', 'REJECT_HARD_NEGATIVE', 'ABSTAIN'])],
            'ai.*.abstained' => ['required', 'boolean:strict'],
            'ai.*.modelVersion' => ['required', 'string', 'max:80'],
            'ai.*.featureVersion' => ['required', 'string', 'max:80'],
            'ai.*.timestamp' => ['required', 'integer:strict', 'min:0'],
            'ai.*.explanation' => ['required', 'string', 'max:500'],
            'rescuePriority' => ['present', 'array', 'list'],
            'rescuePriority.*' => ['required', 'string', 'distinct'],
            'hardware' => ['required', 'array:connectionState,packetRateHz,packetLossRate'],
            'hardware.connectionState' => ['required', Rule::in(['CONNECTED', 'DEGRADED', 'DISCONNECTED'])],
            'hardware.packetRateHz' => ['required', 'numeric:strict', 'min:0'],
            'hardware.packetLossRate' => $unit,
            'timeline' => ['present', 'array', 'list'],
            'timeline.*' => ['required', 'array:eventId,timestamp,level,message'],
            'timeline.*.eventId' => ['required', 'string', 'distinct'],
            'timeline.*.timestamp' => ['required', 'integer:strict', 'min:0'],
            'timeline.*.level' => ['required', Rule::in(['INFO', 'WARNING', 'ERROR'])],
            'timeline.*.message' => ['required', 'string', 'max:500'],
        ];
        foreach (['radar', 'thermal', 'acoustic'] as $sensor) {
            $prefix = 'sensors.'.$sensor;
            $rules[$prefix] = ['required', 'array:available,rawScore,health,quality'];
            $rules[$prefix.'.available'] = ['required', 'boolean:strict'];
            $rules[$prefix.'.rawScore'] = ['present', 'nullable', 'numeric:strict', 'between:0,1'];
            $rules[$prefix.'.health'] = $unit;
            $rules[$prefix.'.quality'] = $unit;
        }
        $validator = Validator::make(['state' => $state], [
            'state' => ['required', 'array:schemaVersion,timestamp,mission,probe,sensors,fusion,localization,victims,ai,rescuePriority,hardware,timeline'],
        ] + collect($rules)->mapWithKeys(fn (array $rule, string $key): array => ['state.'.$key => $rule])->all());

        $validator->after(function ($validator) use ($state): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($state['sensors'] as $name => $sensor) {
                if (($sensor['available'] && $sensor['rawScore'] === null) || (! $sensor['available'] && ($sensor['rawScore'] !== null || $sensor['quality'] != 0))) {
                    $validator->errors()->add('state.sensors.'.$name, 'Unavailable sensors require a null score and zero quality; available sensors require a score.');
                }
            }
            $candidateIds = array_column($state['localization'], 'candidateId');
            $trackIds = array_column($state['victims'], 'victimTrackId');
            foreach ($state['victims'] as $index => $victim) {
                if (! in_array($victim['candidateId'], $candidateIds, true)) {
                    $validator->errors()->add('state.victims.'.$index, 'Victim track must reference a localized candidate.');
                }
            }
            foreach ($state['rescuePriority'] as $trackId) {
                if (! in_array($trackId, $trackIds, true)) {
                    $validator->errors()->add('state.rescuePriority', 'Rescue priority must reference an existing victim track.');
                }
            }
            foreach ($state['ai'] as $index => $ai) {
                $expectedClass = match ($ai['decision']) {
                    'ACCEPT_TRUE_TRACK' => 'TRUE_TRACK',
                    'REJECT_HARD_NEGATIVE' => 'HARD_NEGATIVE',
                    default => null,
                };
                if (! in_array($ai['candidateId'], $candidateIds, true) || $ai['abstained'] !== ($ai['decision'] === 'ABSTAIN') || $ai['classification'] !== $expectedClass) {
                    $validator->errors()->add('state.ai.'.$index, 'AI candidate, classification and abstention must be consistent.');
                }
            }
        });

        return $validator->validate()['state'];
    }
}
