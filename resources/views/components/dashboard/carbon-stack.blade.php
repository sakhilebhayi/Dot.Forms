{{-- Generative artwork from the Carbon Book itself: a pad of three carbon sheets, the top one with its answer lines and
     a blank stamp box. Drawn from the system's tokens; decorative, so hidden from assistive technology; no text in it. --}}
@props(['size' => 'lg'])
<svg {{ $attributes->class(['cb-stack', 'cb-stack--'.$size]) }} viewBox="0 0 168 120" width="{{ $size === 'sm' ? 112 : 168 }}" height="{{ $size === 'sm' ? 80 : 120 }}" aria-hidden="true" focusable="false">
    <rect class="cb-stack__s cb-stack__s3" x="20" y="22" width="132" height="92" rx="3"/>
    <rect class="cb-stack__s cb-stack__s2" x="12" y="14" width="132" height="92" rx="3"/>
    <g class="cb-stack__s1">
        <rect class="cb-stack__top" x="4" y="6" width="132" height="92" rx="3"/>
        <line class="cb-stack__head" x1="16" x2="124" y1="26" y2="26"/>
        <rect class="cb-stack__serial" x="16" y="16" width="34" height="4" rx="1"/>
        <line class="cb-stack__dot" x1="16" x2="124" y1="44" y2="44"/>
        <line class="cb-stack__dot" x1="16" x2="124" y1="58" y2="58"/>
        <line class="cb-stack__dot" x1="16" x2="96" y1="72" y2="72"/>
        <rect class="cb-stack__stamp" x="92" y="76" width="34" height="14" rx="2"/>
    </g>
</svg>
