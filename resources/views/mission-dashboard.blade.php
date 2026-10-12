<x-mission-layout>
    <header class="page-header">
        <div><p class="breadcrumb">Operations / {{ $state['mission']['missionId'] }}</p><h1>Mission overview</h1><p class="subtitle">Operational evidence, hardware health and rescue priorities.</p></div>
        <form class="scenario-form" method="GET" action="{{ route('mission.dashboard') }}">
            <label for="scenario">Demo scenario</label>
            <div class="scenario-controls"><select id="scenario" name="scenario">
                @foreach (['normal' => 'Normal operation', 'degraded' => 'Degraded hardware', 'disconnected' => 'Disconnected hardware', 'abstention' => 'AI abstention'] as $value => $label)
                    <option value="{{ $value }}" @selected($scenario === $value)>{{ $label }}</option>
                @endforeach
            </select><button type="submit">Load</button></div>
        </form>
    </header>
    <section class="mission-banner" aria-label="Mission summary">
        <div><div class="banner-meta">{{ $state['mission']['mode'] }} demonstration <span>Mission {{ $state['mission']['missionId'] }}</span></div><h2>{{ ucfirst(strtolower($state['mission']['status'])) }}</h2><p>Snapshot: {{ gmdate('d M Y, H:i:s', intdiv($state['timestamp'], 1000)) }} UTC</p></div>
        <div class="banner-stat"><span>Elapsed time</span><strong>{{ intdiv($state['mission']['elapsedSeconds'], 60) }}<small> min </small>{{ $state['mission']['elapsedSeconds'] % 60 }}<small> sec</small></strong></div>
        <div class="banner-stat"><span>Search coverage</span><strong>{{ number_format($state['mission']['coverage'] * 100, 0) }}<small>%</small></strong><meter min="0" max="1" value="{{ $state['mission']['coverage'] }}" aria-label="Search coverage">{{ $state['mission']['coverage'] * 100 }}%</meter></div>
    </section>
    @if ($state['hardware']['connectionState'] !== 'CONNECTED')
        <div class="notice" role="status"><strong>{{ $state['hardware']['connectionState'] === 'DISCONNECTED' ? 'Hardware disconnected.' : 'Hardware link degraded.' }}</strong> {{ $state['hardware']['connectionState'] === 'DISCONNECTED' ? 'Last known results are displayed; they are not new live evidence.' : 'Check sensor availability and packet loss before interpreting results.' }}</div>
    @endif
    <div class="overview-grid">
        <section class="panel"><div class="panel-heading"><h2>Sensor evidence</h2><span>Normalized scores</span></div>
            <div class="table-scroll"><table><caption class="sr-only">Sensor availability, raw score, health and measurement quality</caption><thead><tr><th>Sensor</th><th>Availability</th><th>Score</th><th>Health</th><th>Quality</th></tr></thead><tbody>
                @foreach ($state['sensors'] as $name => $sensor)
                    <tr><th scope="row">{{ ucfirst($name) }}</th><td><span class="availability {{ $sensor['available'] ? 'is-available' : 'is-unavailable' }}">{{ $sensor['available'] ? 'Available' : 'Unavailable' }}</span></td><td>{{ $sensor['rawScore'] === null ? '—' : number_format($sensor['rawScore'], 2) }}</td><td>{{ number_format($sensor['health'] * 100, 0) }}%</td><td>{{ number_format($sensor['quality'] * 100, 0) }}%</td></tr>
                @endforeach
            </tbody></table></div><p class="panel-footnote">Availability, sensor health and measurement quality are separate indicators.</p>
        </section>
        <section class="panel"><div class="panel-heading"><h2>Hardware connection</h2><x-status-badge :value="$state['hardware']['connectionState']" /></div>
            <dl class="metric-list"><div><dt>Packet rate</dt><dd>{{ number_format($state['hardware']['packetRateHz'], 1) }} <small>Hz</small></dd></div><div><dt>Packet loss</dt><dd>{{ number_format($state['hardware']['packetLossRate'] * 100, 1) }}<small>%</small></dd></div><div><dt>Probe state</dt><dd class="text-metric">{{ ucfirst(strtolower($state['probe']['state'])) }}</dd></div><div><dt>Probe position</dt><dd class="text-metric">({{ $state['probe']['x'] }}, {{ $state['probe']['y'] }})</dd></div></dl>
        </section>
        <section class="panel"><div class="panel-heading"><h2>Victim tracks</h2><span>{{ count($state['victims']) }} active {{ count($state['victims']) === 1 ? 'track' : 'tracks' }}</span></div>
            <div class="table-scroll"><table><caption class="sr-only">Estimated victim positions, confidence, vitality and priority</caption><thead><tr><th>Track</th><th>Estimated position</th><th>Confidence</th><th>Vitality</th><th>Priority</th></tr></thead><tbody>
                @forelse ($state['victims'] as $victim)
                    <tr><th scope="row">{{ $victim['victimTrackId'] }}<span class="cell-detail">{{ ucfirst(strtolower($victim['status'])) }} / {{ $victim['candidateId'] }}</span></th><td>({{ $victim['estimatedPosition']['x'] }}, {{ $victim['estimatedPosition']['y'] }})</td><td>{{ number_format($victim['confidence'] * 100, 0) }}%</td><td>{{ number_format($victim['vitality'] * 100, 0) }}%</td><td><x-status-badge :value="$victim['priority']" /></td></tr>
                @empty
                    <tr><td colspan="5" class="empty-state">No victim tracks in this snapshot.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="priority-strip"><strong>Rescue order</strong><span>{{ count($state['rescuePriority']) ? implode(' → ', $state['rescuePriority']) : 'No rescue priority assigned' }}</span></div>
        </section>
        <section class="panel"><div class="panel-heading"><h2>Fusion & localization</h2><span>Upstream results</span></div>
            <dl class="metric-list"><div><dt>Fusion score</dt><dd>{{ number_format($state['fusion']['fusionScore'], 2) }}</dd></div><div><dt>Fusion confidence</dt><dd>{{ number_format($state['fusion']['confidence'] * 100, 0) }}<small>%</small></dd></div></dl>
            @forelse ($state['localization'] as $location)
                <div class="location-row"><strong>{{ $location['candidateId'] }}</strong><span>({{ $location['x'] }}, {{ $location['y'] }})</span><span>{{ number_format($location['confidence'] * 100, 0) }}% confidence</span></div>
            @empty
                <p class="empty-state">No localized candidates in this snapshot.</p>
            @endforelse
        </section>
        <section class="panel"><div class="panel-heading"><h2>AI decision support</h2><span>Probability & uncertainty</span></div>
            @forelse ($state['ai'] as $ai)
                <article class="ai-result"><div class="ai-heading"><h3>Candidate {{ $ai['candidateId'] }}</h3><x-status-badge :value="$ai['decision']" /></div>
                    <dl class="metric-list"><div><dt>True-track probability</dt><dd>{{ number_format($ai['probability'] * 100, 0) }}<small>%</small></dd></div><div><dt>Uncertainty</dt><dd>{{ number_format($ai['uncertainty'] * 100, 0) }}<small>%</small></dd></div></dl>
                    @if ($ai['abstained'])<p class="notice notice-inline">AI abstained. No classification was issued.</p>@endif
                    <p>{{ $ai['explanation'] }}</p><p class="panel-footnote">Model: {{ $ai['modelVersion'] }} / Features: {{ $ai['featureVersion'] }}<br>Decision snapshot: {{ gmdate('H:i:s', intdiv($ai['timestamp'], 1000)) }} UTC</p>
                </article>
            @empty
                <p class="empty-state">No AI decisions in this snapshot.</p>
            @endforelse
        </section>
        <section class="panel"><div class="panel-heading"><h2>Mission timeline</h2><span>Recorded events</span></div>
            <ol class="timeline">
                @forelse ($state['timeline'] as $event)
                    <li><time datetime="{{ gmdate('c', intdiv($event['timestamp'], 1000)) }}">{{ gmdate('H:i:s', intdiv($event['timestamp'], 1000)) }}</time><div><x-status-badge :value="$event['level']" /><p>{{ $event['message'] }}</p></div></li>
                @empty
                    <li class="empty-state">No events recorded.</li>
                @endforelse
            </ol>
        </section>
    </div>
    <footer class="page-footer"><span>Demonstration data · All event times in UTC</span><a href="{{ route('mission.state', ['scenario' => $scenario]) }}">View mission JSON</a></footer>
</x-mission-layout>
