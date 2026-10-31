{{-- A content element on the published document (R-31): unboxed, unnumbered.
     Expects: $field (FormField). --}}
@php
    $opts = $field->options ?? [];
@endphp

@if ($field->type === 'heading')
    <h2 class="cb-doc__h2">{{ $field->label }}</h2>
@elseif ($field->type === 'text_block')
    <div class="cb-doc__prose">
        @foreach (preg_split('/\n{2,}/', trim((string) ($opts['body'] ?? ''))) as $paragraph)
            @if (trim($paragraph) !== '')<p>{{ $paragraph }}</p>@endif
        @endforeach
    </div>
@elseif ($field->type === 'image')
    @if (! empty($opts['image_path']))
        <figure class="cb-doc__figure">
            <img src="{{ asset('storage/' . $opts['image_path']) }}" alt="{{ $field->label }}" loading="lazy">
            @if (! empty($opts['helper_text']))<figcaption>{{ $opts['helper_text'] }}</figcaption>@endif
        </figure>
    @endif
@elseif ($field->type === 'video')
    @php $src = is_array($opts['video'] ?? null) ? \App\Support\VideoEmbed::src($opts['video']) : null; @endphp
    @if ($src)
        <div class="cb-doc__video">
            @if ($field->label)<p class="cb-doc__videotitle">{{ $field->label }}</p>@endif
            <div class="cb-doc__videoframe">
                <iframe src="{{ $src }}" title="{{ $field->label ?: 'Video' }}" loading="lazy" allowfullscreen
                    referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-presentation"></iframe>
            </div>
        </div>
    @endif
@elseif ($field->type === 'divider')
    <hr class="cb-doc__rule">
@endif
