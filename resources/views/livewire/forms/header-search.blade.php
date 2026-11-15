<x-dot.search wire="query" name="q" label="Find a form" placeholder="Find a form" hint="/" :hint-key="false" results-nav enter-first escape-clears
    :action="$this->team ? route('teams.forms', $this->team) : null"
    :form-attributes="['wire:submit.prevent' => 'go']"
    :root-attributes="['wire:ignore.self' => '']">
    <x-slot:results>
        @if (! $this->team)
            <div class="dsr-quick">
                <p class="dsr-foot" role="status">Forms live in a company's book. <a href="{{ route('teams.create') }}">Add your company</a> to set and find forms.</p>
            </div>
        @elseif (! $this->ready())
            <div class="dsr-quick">
                <div class="dsr-head">Go to</div>
                <x-dot.search-row href="{{ route('teams.forms', $this->team) }}" label="The book" tag="All forms" />
                <x-dot.search-row href="{{ route('dashboard.analytics') }}" label="Tallies" tag="Counts" />
                @if ($this->recent->isNotEmpty())
                    <div class="dsr-head">Recent forms</div>
                    @foreach ($this->recent as $form)
                        <x-dot.search-row wire:key="hr-{{ $form->id }}" href="{{ $this->urlFor($form) }}" :code="'FRM-'.str_pad((string) $form->id, 3, '0', STR_PAD_LEFT)" :label="$form->title" />
                    @endforeach
                @endif
                <p class="dsr-foot">Type two letters to find a form by its title or description.</p>
            </div>
        @else
            <div class="dsr-quick">
                @if ($this->forms->isNotEmpty())
                    <div class="dsr-head">Forms</div>
                    @foreach ($this->forms as $form)
                        <x-dot.search-row wire:key="hf-{{ $form->id }}" href="{{ $this->urlFor($form) }}" :code="'FRM-'.str_pad((string) $form->id, 3, '0', STR_PAD_LEFT)" :label="$form->title"
                            :tag="$form->archived_at ? 'Closed' : ($form->is_published ? 'In use' : 'Unset')" />
                    @endforeach
                @else
                    <p class="dsr-foot" role="status">No form matches &ldquo;{{ trim($query) }}&rdquo;. Press Enter to look in the book.</p>
                @endif
            </div>
        @endif
    </x-slot:results>
</x-dot.search>
