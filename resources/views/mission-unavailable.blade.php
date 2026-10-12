<x-mission-layout title="Mission data unavailable">
    <section class="error-state"><span class="error-code">503</span><h1>Mission data unavailable</h1><p>The mission snapshot could not be loaded or did not pass validation. No operational data is displayed.</p><p>Check the configured data source and contract before trying again.</p><a class="button" href="{{ route('mission.dashboard') }}">Try again</a></section>
</x-mission-layout>
