{{-- One element on the living master (R-31): the document look, nothing interactive.
     Expects: $field (the builder's array) and $qNo (number for collecting fields, else null). --}}
@php
    use App\Support\FieldTypes;
    use App\Support\VideoEmbed;

    $type = $field['type'];
    $choices = collect(explode(',', (string) ($field['options'] ?? '')))->map(fn ($o) => trim($o))->filter()->values();
@endphp

@if ($type === FieldTypes::PAGE_BREAK)
    <p class="cb-el__break">⤵ PAGE BREAK — the published form starts a new page here</p>
@elseif ($type === 'heading')
    <h2 class="cb-doc__h2" style="margin:0;">{{ $field['label'] ?: 'Section heading' }}</h2>
@elseif ($type === 'text_block')
    <div class="cb-doc__prose" style="margin:0;">
        @if (filled($field['body'] ?? null))
            @foreach (preg_split('/\n{2,}/', trim((string) $field['body'])) as $paragraph)
                @if (trim($paragraph) !== '')<p>{{ $paragraph }}</p>@endif
            @endforeach
        @else
            <p class="cb-el__hint">Write the text in the panel on the right.</p>
        @endif
    </div>
@elseif ($type === 'image')
    @if (filled($field['image_path'] ?? null))
        <figure class="cb-doc__figure" style="margin:0;">
            <img src="{{ asset('storage/' . $field['image_path']) }}" alt="{{ $field['label'] }}">
            @if (filled($field['helper_text'] ?? null))<figcaption>{{ $field['helper_text'] }}</figcaption>@endif
        </figure>
    @else
        <p class="cb-el__hint">▣ Choose an image in the panel on the right, then save.</p>
    @endif
@elseif ($type === 'video')
    @php $video = VideoEmbed::parse($field['video_url'] ?? null); @endphp
    @if ($video)
        <div class="cb-doc__video" style="margin:0;">
            @if (filled($field['label'] ?? null))<p class="cb-doc__videotitle">{{ $field['label'] }}</p>@endif
            <div class="cb-doc__videoframe">
                <iframe src="{{ VideoEmbed::src($video) }}" title="{{ $field['label'] ?: 'Video' }}" loading="lazy" sandbox="allow-scripts allow-same-origin allow-presentation"></iframe>
            </div>
        </div>
    @else
        <p class="cb-el__hint">▶ Paste a YouTube or Vimeo link in the panel on the right.</p>
    @endif
@elseif ($type === 'divider')
    <hr class="cb-doc__rule" style="margin:4px 0;">
@else
    <div class="cb-q" style="margin:0;">
        @if ($qNo !== null)<span class="cb-q__no">{{ str_pad((string) $qNo, 2, '0', STR_PAD_LEFT) }}</span>@endif
        <label>{{ $field['label'] ?: FieldTypes::label($type) }}@if (! empty($field['required'])) <span aria-hidden="true" style="color:#b3362a;">*</span>@endif</label>
        @if (filled($field['helper_text'] ?? null))<p class="cb-help">{{ $field['helper_text'] }}</p>@endif

        @if ($type === 'textarea')
            <div class="cb-el__mockarea"></div>
        @elseif (in_array($type, ['select', 'country'], true))
            <div class="cb-el__mockline">{{ $field['placeholder'] ?: 'Select an option' }} <span style="float:right">▾</span></div>
        @elseif (in_array($type, ['radio', 'multiselect'], true))
            @forelse ($choices as $option)
                <span class="cb-choice" style="padding:3px 0;"><span class="cb-el__mark">{{ $type === 'radio' ? '◉' : '☑' }}</span> {{ $option }}</span><br>
            @empty
                <p class="cb-el__hint">Add options in the panel on the right.</p>
            @endforelse
        @elseif ($type === 'yesno')
            <span class="cb-choice" style="display:inline-flex;gap:6px;margin-right:16px;"><span class="cb-el__mark">◉</span> Yes</span>
            <span class="cb-choice" style="display:inline-flex;gap:6px;"><span class="cb-el__mark">◉</span> No</span>
        @elseif (in_array($type, ['checkbox', 'consent'], true))
            <span class="cb-choice"><span class="cb-el__mark">☑</span> {{ $type === 'consent' ? 'I agree' : 'I confirm' }}</span>
        @elseif ($type === 'rating' || $type === 'scale')
            <div class="cb-steprow">
                @foreach (range($type === 'rating' ? 1 : 0, $type === 'rating' ? 5 : 10) as $n)
                    <label><span>{{ $n }}</span></label>
                @endforeach
            </div>
        @elseif ($type === 'address')
            <div class="cb-el__mockline">Street</div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
                <div class="cb-el__mockline">City</div><div class="cb-el__mockline">Province</div><div class="cb-el__mockline">Code</div>
            </div>
        @elseif ($type === 'matrix')
            @php $mockRows = collect(explode(',', (string) ($field['rows'] ?? '')))->map(fn ($r) => trim($r))->filter(); @endphp
            @if ($choices->isEmpty() || $mockRows->isEmpty())
                <p class="cb-el__hint">Set the rows and the columns in the panel on the right.</p>
            @else
                <div class="cb-matrix"><table>
                    <thead><tr><th></th>@foreach ($choices as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                    <tbody>@foreach ($mockRows as $r)<tr><th>{{ $r }}</th>@foreach ($choices as $c)<td><span class="cb-el__mark">◉</span></td>@endforeach</tr>@endforeach</tbody>
                </table></div>
            @endif
        @elseif ($type === 'ranking')
            @forelse ($choices as $i => $option)
                <div class="cb-ranking__row"><span class="cb-serial" style="font-size:12px;">{{ $i + 1 }}.</span> {{ $option }}</div>
            @empty
                <p class="cb-el__hint">Add the items to rank in the panel on the right.</p>
            @endforelse
        @elseif ($type === 'calculated')
            <p class="cb-el__hint">∑ {{ $field['formula'] ?: 'Write the formula in the panel on the right.' }}</p>
        @elseif ($type === 'file')
            <div class="cb-el__mockline">↥ {{ $field['placeholder'] ?: 'Choose a file' }}</div>
        @elseif ($type === 'signature')
            <div class="cb-el__mocksig">✎ Signed in the box</div>
        @elseif ($type === 'hidden')
            <p class="cb-el__hint">Recorded invisibly with every copy: <span class="cb-serial" style="font-size:12px;">{{ $field['default_value'] ?: 'no value set' }}</span></p>
        @else
            <div class="cb-el__mockline">{{ $field['placeholder'] ?: ' ' }}</div>
        @endif
    </div>
@endif
