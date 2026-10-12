@props(['value'])
@php
    $tone = match ($value) {
        'CONNECTED', 'CONFIRMED', 'ACCEPT_TRUE_TRACK', 'INFO' => 'good',
        'DEGRADED', 'TENTATIVE', 'ABSTAIN', 'WARNING', 'PAUSED', 'HIGH' => 'warning',
        'DISCONNECTED', 'FAILED', 'ERROR', 'LOST', 'REJECT_HARD_NEGATIVE' => 'danger',
        default => 'neutral',
    };
@endphp
<span {{ $attributes->class(['badge', 'badge-'.$tone]) }}>{{ ucfirst(strtolower(str_replace('_', ' ', $value))) }}</span>
