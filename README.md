# USAR mission dashboard

USAR-24: backend and operational dashboard integration foundation, implemented with Laravel 13 and Blade. The application serves validated MissionState v1.0 fixtures without a live MATLAB, AI or hardware connection.

## Run locally

Requires PHP 8.3+, Composer, and a Node version supported by Vite 8 (20.19+ or 22.12+). For a fresh checkout:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
npm run build
```

Copy the environment file only if it does not already exist. The two read-only mission routes do not start sessions and require no database. If using Laravel's other local utilities, these optional values also avoid database-backed services:

```dotenv
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Then run `php artisan serve` and open http://127.0.0.1:8000. Use `npm run dev` in a second terminal when changing styles. No mission tables, user accounts or database migrations are required by USAR-24.

## Read-only routes

- `GET /`: Blade mission dashboard.
- `GET /api/v1/mission-state`: validated operational snapshot, with JSON errors regardless of the Accept header.
- Optional `?scenario=normal|degraded|disconnected|abstention` on either route.
- Unknown/non-string scenarios return validation errors (422 for JSON requests).
- Missing, malformed or invalid upstream snapshots return 503 with a generic message. The HTML page displays no operational data on failure.
- There are no mutation endpoints, evaluation endpoints or live hardware controls.

## Data-source boundary

`MissionStateSource` supplies a complete assembled operational snapshot. `FixtureMissionStateSource` reads allowlisted filenames from `config/mission.php`, not from user-supplied paths. `MissionStateService` validates the snapshot before either HTML or JSON can use it. Replace the binding in `AppServiceProvider` with a live source implementing the same interface when integration is ready.

The backend and Blade views consume upstream scientific outputs. They do not calculate HAIF fusion, localization, AI classifications, vitality or rescue priority. Percent conversions and labels are presentation only. Fixture names and a visible demonstration notice distinguish demo data from live telemetry. Disconnected mode retains a labeled last-known snapshot.

Fixtures are in `resources/fixtures`. Four valid scenarios are selectable; the malformed JSON fixture is test-only and is not selectable in the UI.

## MissionState v1.0 draft contract

This is a local integration draft based on the supplied Sprint-0 story guide. The referenced `05_Interface_Contracts.md` was not provided. Confirm exact fields, units, enumerations and cardinalities with that approved contract before connecting real producers. In particular, this draft uses lists for `ai` and `localization` to support multiple candidates; the guide's abbreviated example shows one AI object.

Every section below is required. List sections may be empty. Unknown keys are rejected at every structured level; JSON numbers and booleans must have their actual types.

| Section | Fields / semantics |
| --- | --- |
| Root | `schemaVersion` exactly `1.0`; `timestamp` nonnegative integer, Unix milliseconds; the sections below |
| mission | `missionId` string; `mode` SIMULATION/HIL; `status` READY/SEARCHING/PAUSED/COMPLETED/FAILED; `elapsedSeconds` nonnegative integer; `coverage` [0,1] |
| probe | numeric `x`, `y` in upstream coordinate units; `state` IDLE/MOVING/SCANNING/STOPPED |
| sensors | required radar/thermal/acoustic objects: boolean `available`, nullable `rawScore`, `health`, `quality`; numeric values [0,1]. Unavailable implies null score and zero quality; available requires a score. Health remains independent. |
| fusion | `fusionScore`, `confidence`, both [0,1] |
| localization[] | unique `candidateId`, numeric `x`, `y`, `confidence` [0,1]; estimated operational coordinates |
| victims[] | unique `victimTrackId` and `candidateId`; `status` TENTATIVE/CONFIRMED/LOST; `estimatedPosition.x/y`; `confidence`, `vitality` [0,1]; `priority` HIGH/MEDIUM/LOW/UNASSIGNED |
| ai[] | unique `candidateId`; nullable `classification`; `probability` (true-track probability), `uncertainty` [0,1]; `decision`, boolean `abstained`; `modelVersion`, `featureVersion`, millisecond `timestamp`, string `explanation` |
| rescuePriority[] | unique victim track IDs, in the order supplied by upstream |
| hardware | `connectionState` CONNECTED/DEGRADED/DISCONNECTED; `packetRateHz` nonnegative; `packetLossRate` [0,1] |
| timeline[] | unique `eventId`, millisecond `timestamp`, `level` INFO/WARNING/ERROR, string `message`; rendered in supplied order |

AI ACCEPT_TRUE_TRACK requires TRUE_TRACK and abstained=false; REJECT_HARD_NEGATIVE requires HARD_NEGATIVE and abstained=false; ABSTAIN requires classification=null and abstained=true. Victim and AI candidate IDs must reference localization entries; rescue priority IDs must reference victim tracks.

All dates in the dashboard are explicitly UTC. All positions are estimated, never evaluation coordinates. Fields such as groundTruth, trueLabel and trueCoordinates are rejected, including nested extras. No evaluation data is released even after completion. Free-text explanations/events must still be sanitized by their producers against semantic evaluation leakage; a structural validator cannot establish the provenance of text or numeric values.

## Validation

```powershell
php artisan test --compact tests/Feature/MissionStateTest.php
php artisan test --compact
php vendor/bin/pint --dirty --format agent
npm run build
```

Coverage includes full fixture-to-API/Blade paths, all demo scenarios, empty collections, missing sections, schema/type/range failures, inconsistent sensor/AI states, unknown references, nested oracle fields, escaped text and missing/malformed source failures.

## USAR-24 acceptance evidence

- MissionState v1.0 draft is documented and validated.
- Backend consumes and serves representative fixtures.
- Blade dashboard works independently of live scientific modules.
- HardwareStatus, AIOutput (including abstention), victim, fusion, localization and rescue information are represented.
- Operational snapshots reject extra evaluation fields before rendering or serialization.
- Fixture-to-render smoke coverage is included.

This scope excludes authentication, persistence, scientific algorithms and deployment. No GitHub push, pull request or Jira status update is performed automatically.
