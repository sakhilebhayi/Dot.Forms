<div>
    <div class="cb-pagehead">
        <div>
            <h1 class="cb-h1">Master templates</h1>
            <p class="cb-sub">Start from a form that already works — every one opens as a draft in the press.</p>
        </div>
        <a href="{{ route('teams.forms', $team) }}" class="cb-btn cb-btn--quiet">← Back to the book</a>
        <a href="{{ route('teams.forms.ai-builder', $team) }}" class="cb-btn">Set with AI instead</a>
    </div>

    @if (session('status'))
        <p class="cb-note" role="status">{{ session('status') }}</p>
    @endif

    @if ($teamTemplates->isNotEmpty())
        <section class="cb-sec">
            <div class="cb-sec__head"><h2>{{ $team->name }}'s own templates</h2></div>
            <div class="cb-register">
                @foreach ($teamTemplates as $template)
                    <div class="cb-bookrow" style="grid-template-columns:minmax(0,1fr) 150px 190px;" wire:key="tt-{{ $template->id }}">
                        <span>
                            <b>{{ $template->name }}</b><br>
                            <span class="cb-dim">{{ $template->description ?: (count($template->payload['fields'] ?? []).' fields') }}</span>
                        </span>
                        <span class="cb-dim">{{ count($template->payload['fields'] ?? []) }} {{ Str::plural('field', count($template->payload['fields'] ?? [])) }}</span>
                        <span style="display:flex;gap:6px;justify-content:flex-end;">
                            <button class="cb-btn cb-btn--primary cb-btn--sm" wire:click="useTeamTemplate({{ $template->id }})">Use it</button>
                            <button class="cb-btn cb-btn--quiet cb-btn--sm" style="color:var(--cb-stamp-red)" wire:click="deleteTeamTemplate({{ $template->id }})" wire:confirm="Remove this template? Forms already made from it are untouched.">Remove</button>
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @foreach ($builtIns as $group => $templates)
        <section class="cb-sec">
            <div class="cb-sec__head"><h2>{{ $group }}</h2></div>
            <div class="cb-register">
                @foreach ($templates as $slug => $template)
                    <div class="cb-bookrow" style="grid-template-columns:minmax(0,1fr) 150px 110px;" wire:key="bt-{{ $slug }}">
                        <span>
                            <b>{{ $template['name'] }}</b><br>
                            <span class="cb-dim">{{ $template['description'] }}</span>
                        </span>
                        <span class="cb-dim">{{ count($template['fields']) }} {{ Str::plural('field', count($template['fields'])) }}</span>
                        <span style="display:flex;justify-content:flex-end;">
                            <button class="cb-btn cb-btn--primary cb-btn--sm" wire:click="useTemplate('{{ $slug }}')">Use it</button>
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
