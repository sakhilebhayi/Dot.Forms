{{-- Copies per day for the selected master: one series, so no legend (the heading names it). Server-rendered inline SVG,
     bars start at zero, no external library. Every bar carries its own <title>; the same numbers are a table below.
     Props: $series = list of ['date' => Carbon, 'count' => int], oldest first. --}}
@php
    $n = count($series);
    $max = max(1, collect($series)->max('count'));
    $left = 24; $right = 440; $base = 120; $plotH = 100; $barW = 18;
    $pitch = ($right - $left) / $n;
    $busy = collect($series)->filter(fn ($d) => $d['count'] === $max && $max > 0)->take(3)->keys()->all();
    $first = $series[0]['date']; $last = $series[$n - 1]['date'];
    $mid = $series[intdiv($n, 2)]['date'];
    $total = collect($series)->sum('count');
    $busiest = collect($series)->filter(fn ($d) => $d['count'] === $max)->map(fn ($d) => $d['date']->format('j F'))->take(3)->implode(' and ');
    $uid = 'cpd-'.($formId ?? 0);
@endphp
<svg class="cb-chart" viewBox="0 0 440 146" role="img" aria-labelledby="{{ $uid }}-t {{ $uid }}-d">
    <title id="{{ $uid }}-t">Copies per day, {{ $first->format('j F') }} to {{ $last->format('j F') }}</title>
    <desc id="{{ $uid }}-d">Bars starting at zero. Busiest: {{ $busiest }} with {{ $max }} {{ \Illuminate\Support\Str::plural('copy', $max) }}. {{ $total }} {{ \Illuminate\Support\Str::plural('copy', $total) }} in {{ $n }} days.</desc>
    <line class="cb-chart__grid" x1="{{ $left }}" x2="{{ $right }}" y1="{{ $base - $plotH }}" y2="{{ $base - $plotH }}"/>
    <text x="0" y="{{ $base - $plotH + 4 }}">{{ $max }}</text>
    <line class="cb-chart__base" x1="{{ $left }}" x2="{{ $right }}" y1="{{ $base }}" y2="{{ $base }}"/>
    <text x="0" y="{{ $base + 4 }}">0</text>
    @foreach ($series as $i => $day)
        @php
            $h = $day['count'] === 0 ? 2 : max(3, round($day['count'] / $max * $plotH, 1));
            $x = round($left + $i * $pitch + ($pitch - $barW) / 2, 1);
            $label = $day['date']->format('j M').': '.$day['count'].' '.\Illuminate\Support\Str::plural('copy', $day['count']);
        @endphp
        <rect @class(['cb-chart__bar', 'cb-chart__bar--zero' => $day['count'] === 0]) x="{{ $x }}" y="{{ $base - $h }}" width="{{ $barW }}" height="{{ $h }}" rx="{{ $day['count'] === 0 ? 0 : 2 }}"><title>{{ $label }}</title></rect>
        @if (in_array($i, $busy, true))
            <text class="cb-chart__val" x="{{ $x + $barW / 2 }}" y="{{ $base - $h - 6 }}" text-anchor="middle">{{ $day['count'] }}</text>
        @endif
    @endforeach
    <text x="{{ $left }}" y="140">{{ $first->format('j M') }}</text>
    <text x="{{ ($left + $right) / 2 }}" y="140" text-anchor="middle">{{ $mid->format('j M') }}</text>
    <text x="{{ $right }}" y="140" text-anchor="end">{{ $last->format('j M') }}</text>
</svg>
<details class="cb-desk__table">
    <summary>Values as a table</summary>
    <table>
        <thead><tr><th scope="col">Day</th><th scope="col">Copies</th></tr></thead>
        <tbody>
            @foreach ($series as $day)
                <tr><td>{{ $day['date']->format('j M') }}</td><td>{{ $day['count'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
</details>
