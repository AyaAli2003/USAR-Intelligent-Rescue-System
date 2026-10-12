<?php

use App\Contracts\MissionStateSource;
use App\Services\MissionStateValidator;
use Illuminate\Validation\ValidationException;

function operationalFixture(): array
{
    return json_decode(file_get_contents(resource_path('fixtures/mission-state-normal.json')), true, flags: JSON_THROW_ON_ERROR);
}

function useOperationalState(array $state): void
{
    app()->instance(MissionStateSource::class, new class($state) implements MissionStateSource
    {
        public function __construct(private array $state) {}

        public function load(string $scenario): array
        {
            return $this->state;
        }
    });
}

test('serves the complete validated fixture through the versioned API', function () {
    $expected = operationalFixture();

    $this->getJson('/api/v1/mission-state')
        ->assertOk()
        ->assertExactJson($expected)
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('returns a 422 error page for an invalid HTML scenario without a session', function () {
    $this->get('/?scenario=unknown')
        ->assertUnprocessable()
        ->assertViewIs('mission-invalid-scenario')
        ->assertSee('Unknown demo scenario');
});

test('renders the fixture dashboard for each operational scenario', function (string $scenario, string $visible) {
    $this->get('/?scenario='.$scenario)
        ->assertOk()
        ->assertViewIs('mission-dashboard')
        ->assertSee('Fixture demonstration')
        ->assertSee('M042')
        ->assertSee('V1')
        ->assertSee($visible);
})->with([
    'normal' => ['normal', 'Connected'],
    'degraded' => ['degraded', 'Hardware link degraded.'],
    'disconnected' => ['disconnected', 'Last known results are displayed'],
    'abstention' => ['abstention', 'AI abstained. No classification was issued.'],
]);

test('preserves degraded sensor availability independently of health and quality', function () {
    $this->getJson('/api/v1/mission-state?scenario=degraded')
        ->assertOk()
        ->assertJsonPath('sensors.acoustic.available', false)
        ->assertJsonPath('sensors.acoustic.rawScore', null)
        ->assertJsonPath('sensors.acoustic.health', 0.35)
        ->assertJsonPath('sensors.acoustic.quality', 0)
        ->assertJsonPath('hardware.connectionState', 'DEGRADED');
});

test('serves disconnected state with no new sensor readings', function () {
    $this->getJson('/api/v1/mission-state?scenario=disconnected')
        ->assertOk()
        ->assertJsonPath('hardware.packetRateHz', 0)
        ->assertJsonPath('mission.status', 'PAUSED')
        ->assertJsonPath('sensors.radar.available', false);
});

test('preserves AI abstention and model provenance', function () {
    $this->getJson('/api/v1/mission-state?scenario=abstention')
        ->assertOk()
        ->assertJsonPath('ai.0.classification', null)
        ->assertJsonPath('ai.0.abstained', true)
        ->assertJsonPath('ai.0.decision', 'ABSTAIN')
        ->assertJsonPath('ai.0.modelVersion', 'fixture-model-1.0')
        ->assertJsonPath('rescuePriority', []);
});

test('returns 422 for an unknown or non-string scenario', function (mixed $scenario) {
    $this->getJson('/api/v1/mission-state?'.http_build_query(['scenario' => $scenario]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scenario');
})->with(['unknown' => ['unknown'], 'path traversal' => ['../../.env'], 'array' => [['normal']]]);

test('rejects invalid operational contracts', function (string $path, mixed $value) {
    $state = operationalFixture();
    data_set($state, $path, $value);

    expect(fn () => app(MissionStateValidator::class)->validate($state))
        ->toThrow(ValidationException::class);
})->with([
    'schema mismatch' => ['schemaVersion', '2.0'],
    'unsupported mode' => ['mission.mode', 'LIVE'],
    'invalid mission status' => ['mission.status', 'UNKNOWN'],
    'negative timestamp' => ['timestamp', -1],
    'numeric string' => ['fusion.confidence', '0.88'],
    'out of range confidence' => ['fusion.confidence', 1.1],
    'sensor string boolean' => ['sensors.radar.available', 'true'],
    'sensor missing score' => ['sensors.radar.rawScore', null],
    'unavailable sensor with evidence' => ['sensors.radar.available', false],
    'invalid packet loss' => ['hardware.packetLossRate', -0.1],
    'unknown hardware state' => ['hardware.connectionState', 'UNKNOWN'],
    'AI contradiction' => ['ai.0.abstained', true],
    'AI inconsistent classification' => ['ai.0.classification', 'HARD_NEGATIVE'],
    'unknown candidate' => ['ai.0.candidateId', 'C99'],
    'unknown victim candidate' => ['victims.0.candidateId', 'C99'],
    'unknown rescue track' => ['rescuePriority.0', 'V99'],
    'top level ground truth' => ['groundTruth', ['trueX' => 99]],
    'nested oracle data' => ['victims.0.estimatedPosition.trueX', 99],
    'AI oracle field' => ['ai.0.trueLabel', 'TRUE_TRACK'],
    'sensor unexpected field' => ['sensors.radar.oracle', true],
    'event oracle field' => ['timeline.0.trueCoordinates', [1, 2]],
]);

test('rejects every missing required section', function (string $section) {
    $state = operationalFixture();
    unset($state[$section]);

    expect(fn () => app(MissionStateValidator::class)->validate($state))
        ->toThrow(ValidationException::class);
})->with(['schemaVersion', 'timestamp', 'mission', 'probe', 'sensors', 'fusion', 'localization', 'victims', 'ai', 'rescuePriority', 'hardware', 'timeline']);

test('returns 503 without exposing invalid data or validation internals', function () {
    $state = operationalFixture();
    $state['groundTruth'] = ['secret' => 'evaluation-secret'];
    useOperationalState($state);

    $this->getJson('/api/v1/mission-state')
        ->assertServiceUnavailable()
        ->assertExactJson(['error' => 'MISSION_STATE_UNAVAILABLE', 'message' => 'Mission data is unavailable or invalid.'])
        ->assertDontSee('evaluation-secret');
});

test('renders a safe error page when the upstream snapshot is invalid', function () {
    useOperationalState(['schemaVersion' => '9.0']);

    $this->get('/')
        ->assertServiceUnavailable()
        ->assertViewIs('mission-unavailable')
        ->assertSee('Mission data unavailable')
        ->assertDontSee('9.0');
});

test('returns 503 when the fixture is missing or malformed', function (string $filename) {
    config(['mission.fixtures.normal' => $filename]);

    $this->getJson('/api/v1/mission-state')
        ->assertServiceUnavailable()
        ->assertJsonPath('error', 'MISSION_STATE_UNAVAILABLE');
})->with(['missing' => ['not-present.json'], 'malformed' => ['mission-state-malformed.json']]);

test('renders empty collections without inventing operational results', function () {
    $state = operationalFixture();
    foreach (['localization', 'victims', 'ai', 'rescuePriority', 'timeline'] as $key) {
        $state[$key] = [];
    }
    useOperationalState($state);

    $this->get('/')
        ->assertOk()
        ->assertSee('No victim tracks in this snapshot.')
        ->assertSee('No AI decisions in this snapshot.')
        ->assertSee('No rescue priority assigned');
});

test('escapes upstream explanations and event messages in Blade', function () {
    $state = operationalFixture();
    $state['ai'][0]['explanation'] = '<script>alert("ai")</script>';
    $state['timeline'][0]['message'] = '<script>alert("event")</script>';
    useOperationalState($state);

    $this->get('/')
        ->assertOk()
        ->assertSee($state['ai'][0]['explanation'])
        ->assertSee($state['timeline'][0]['message'])
        ->assertDontSee($state['ai'][0]['explanation'], false)
        ->assertDontSee($state['timeline'][0]['message'], false);
});
