@props(['title' => 'Mission overview'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | USAR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar" aria-label="Project information">
            <a class="brand" href="{{ route('mission.dashboard') }}"><span class="brand-mark" aria-hidden="true">+</span><span>USAR<span class="brand-subtitle">Intelligent rescue system</span></span></a>
            <div class="sidebar-context">Mission workspace</div>
            <a class="nav-item" href="{{ route('mission.dashboard') }}" aria-current="page">Mission overview</a>
            <div class="sidebar-note"><span class="demo-dot" aria-hidden="true"></span>Fixture demonstration<p>Representative data for integration testing. No live mission connection.</p></div>
            <div class="sidebar-footer">Sprint 0<span>Backend & dashboard foundation</span></div>
        </aside>
        <main id="main-content">{{ $slot }}</main>
    </div>
</body>
</html>
