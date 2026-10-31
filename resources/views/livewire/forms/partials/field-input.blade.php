{{-- One field of the published document: label, control, errors. Shared by the
     standard, paged and conversational renderings so the type switch exists once.
     Expects: $field. Structural and hidden types never reach this partial. --}}
@php
    $required = in_array('required', $field->validation_rules ?? [], true);
    $choices = $field->options['choices'] ?? [];
@endphp

<label @if (! in_array($field->type, ['radio', 'yesno', 'checkbox', 'consent', 'multiselect', 'rating', 'scale', 'signature', 'address', 'matrix', 'ranking', 'calculated'], true)) for="field-{{ $field->id }}" @endif>
    {{ $field->label }}@if ($required) <span aria-hidden="true" style="color:#b3362a;">*</span>@endif
</label>

@if (! empty($field->options['helper_text']))
    <p class="cb-help">{{ $field->options['helper_text'] }}</p>
@endif

@if ($field->type === 'textarea')
    <textarea id="field-{{ $field->id }}" wire:model="answers.{{ $field->id }}" rows="4" placeholder="{{ $field->placeholder }}"></textarea>
@elseif ($field->type === 'select' || $field->type === 'country')
    {{-- .live: other fields' visibility can depend on this answer. --}}
    <select id="field-{{ $field->id }}" wire:model.live="answers.{{ $field->id }}">
        <option value="">Select an option</option>
        @foreach ($field->type === 'country' ? \App\Support\FieldTypes::COUNTRIES : $choices as $option)
            <option value="{{ $option }}">{{ $option }}</option>
        @endforeach
    </select>
@elseif ($field->type === 'radio')
    <div>
        @foreach ($choices as $option)
            <label class="cb-choice">
                <input type="radio" wire:model.live="answers.{{ $field->id }}" value="{{ $option }}" name="field-{{ $field->id }}">
                <span>{{ $option }}</span>
            </label>
        @endforeach
    </div>
@elseif ($field->type === 'yesno')
    <div style="display:flex;gap:18px;">
        @foreach (['Yes', 'No'] as $option)
            <label class="cb-choice">
                <input type="radio" wire:model.live="answers.{{ $field->id }}" value="{{ $option }}" name="field-{{ $field->id }}">
                <span>{{ $option }}</span>
            </label>
        @endforeach
    </div>
@elseif ($field->type === 'multiselect')
    <div>
        @foreach ($choices as $option)
            <label class="cb-choice">
                <input type="checkbox" wire:model.live="answers.{{ $field->id }}" value="{{ $option }}">
                <span>{{ $option }}</span>
            </label>
        @endforeach
    </div>
@elseif ($field->type === 'checkbox')
    <label class="cb-choice">
        <input type="checkbox" wire:model.live="answers.{{ $field->id }}">
        <span>I confirm</span>
    </label>
@elseif ($field->type === 'consent')
    <label class="cb-choice">
        <input type="checkbox" wire:model.live="answers.{{ $field->id }}">
        <span>I agree</span>
    </label>
@elseif ($field->type === 'rating' || $field->type === 'scale')
    @php [$from, $to] = $field->type === 'rating' ? [1, 5] : [0, 10]; @endphp
    <div class="cb-steprow" role="radiogroup" aria-label="{{ $field->label }}">
        @foreach (range($from, $to) as $n)
            <label>
                <input type="radio" wire:model.live="answers.{{ $field->id }}" value="{{ $n }}" name="field-{{ $field->id }}">
                <span>{{ $n }}</span>
            </label>
        @endforeach
    </div>
@elseif ($field->type === 'currency')
    <div class="cb-affix"><span aria-hidden="true">R</span>
        <input id="field-{{ $field->id }}" type="number" step="0.01" min="0" inputmode="decimal" wire:model.blur="answers.{{ $field->id }}" placeholder="{{ $field->placeholder ?: '0.00' }}" />
    </div>
@elseif ($field->type === 'percentage')
    <div class="cb-affix cb-affix--after">
        <input id="field-{{ $field->id }}" type="number" step="0.1" min="0" max="100" inputmode="decimal" wire:model.blur="answers.{{ $field->id }}" placeholder="{{ $field->placeholder ?: '0' }}" /><span aria-hidden="true">%</span>
    </div>
@elseif ($field->type === 'address')
    <div class="cb-address">
        <div><label class="cb-sub" for="field-{{ $field->id }}-street">Street</label><input id="field-{{ $field->id }}-street" type="text" wire:model.blur="answers.{{ $field->id }}.street"></div>
        <div><label class="cb-sub" for="field-{{ $field->id }}-city">City / town</label><input id="field-{{ $field->id }}-city" type="text" wire:model.blur="answers.{{ $field->id }}.city"></div>
        <div><label class="cb-sub" for="field-{{ $field->id }}-province">Province</label><input id="field-{{ $field->id }}-province" type="text" wire:model.blur="answers.{{ $field->id }}.province"></div>
        <div><label class="cb-sub" for="field-{{ $field->id }}-code">Postal code</label><input id="field-{{ $field->id }}-code" type="text" wire:model.blur="answers.{{ $field->id }}.code"></div>
    </div>
@elseif ($field->type === 'matrix')
    @php $rows = $field->options['rows'] ?? []; @endphp
    <div class="cb-matrix" role="group" aria-label="{{ $field->label }}">
        <table>
            <thead><tr><th></th>@foreach ($choices as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($rows as $rowIndex => $row)
                    <tr>
                        <th>{{ $row }}</th>
                        @foreach ($choices as $column)
                            <td><input type="radio" aria-label="{{ $row }}: {{ $column }}" name="field-{{ $field->id }}-{{ $rowIndex }}" value="{{ $column }}" wire:model.live="answers.{{ $field->id }}.{{ $rowIndex }}"></td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@elseif ($field->type === 'ranking')
    <div class="cb-ranking" x-data="{
            order: @js(array_values($choices)),
            move(i, d) {
                const j = i + d;
                if (j < 0 || j >= this.order.length) return;
                [this.order[i], this.order[j]] = [this.order[j], this.order[i]];
                $wire.set('answers.{{ $field->id }}', [...this.order], false);
            }
        }" x-init="$wire.set('answers.{{ $field->id }}', [...order], false)">
        <template x-for="(item, i) in order" :key="item">
            <div class="cb-ranking__row">
                <span class="cb-serial" style="font-size:12px;" x-text="(i + 1) + '.'"></span>
                <span x-text="item"></span>
                <span style="margin-left:auto;display:flex;gap:4px;">
                    <button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" @click="move(i, -1)" :disabled="i === 0" aria-label="Move up">↑</button>
                    <button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" @click="move(i, 1)" :disabled="i === order.length - 1" aria-label="Move down">↓</button>
                </span>
            </div>
        </template>
        <p class="cb-help" style="margin-top:6px;">1 is the most important.</p>
    </div>
@elseif ($field->type === 'calculated')
    <p class="cb-help" style="margin:0;">Calculated automatically when you submit.</p>
@elseif ($field->type === 'file')
    <input id="field-{{ $field->id }}" type="file" wire:model="uploads.{{ $field->id }}" />
@elseif ($field->type === 'signature')
    <div class="cb-sigpad" x-data="{
            drawing: false,
            drawn: false,
            ctx: null,
            start(e) {
                const c = $refs.pad; this.ctx = c.getContext('2d');
                this.ctx.lineWidth = 2; this.ctx.lineCap = 'round'; this.ctx.strokeStyle = '#191a1c';
                this.drawing = true; const p = this.point(e);
                this.ctx.beginPath(); this.ctx.moveTo(p.x, p.y); e.preventDefault();
            },
            move(e) {
                if (! this.drawing) return;
                const p = this.point(e); this.ctx.lineTo(p.x, p.y); this.ctx.stroke(); this.drawn = true; e.preventDefault();
            },
            stop() {
                if (! this.drawing) return;
                this.drawing = false;
                if (this.drawn) { $wire.set('answers.{{ $field->id }}', $refs.pad.toDataURL('image/png'), false); }
            },
            clear() {
                const c = $refs.pad; c.getContext('2d').clearRect(0, 0, c.width, c.height);
                this.drawn = false; $wire.set('answers.{{ $field->id }}', null, false);
            },
            point(e) {
                const r = $refs.pad.getBoundingClientRect();
                const t = e.touches ? e.touches[0] : e;
                return { x: (t.clientX - r.left) * ($refs.pad.width / r.width), y: (t.clientY - r.top) * ($refs.pad.height / r.height) };
            }
        }">
        <canvas x-ref="pad" width="560" height="160"
            @pointerdown="start($event)" @pointermove="move($event)" @pointerup="stop()" @pointerleave="stop()"></canvas>
        <div class="cb-sigpad__bar">
            <span>Sign in the box with your finger or mouse.</span>
            <button type="button" class="cb-btn cb-btn--quiet cb-btn--sm" @click="clear()">Clear</button>
        </div>
    </div>
@else
    {{-- .blur: visibility re-evaluates without a round-trip per keystroke. --}}
    @php
        $inputType = match ($field->type) {
            'email' => 'email', 'number' => 'number', 'date' => 'date',
            'phone' => 'tel', 'url' => 'url', 'time' => 'time', 'datetime' => 'datetime-local',
            default => 'text',
        };
    @endphp
    <input id="field-{{ $field->id }}" type="{{ $inputType }}" wire:model.blur="answers.{{ $field->id }}" placeholder="{{ $field->placeholder }}" />
@endif

@error('answers.' . $field->id)
    <p class="cb-error" role="alert">{{ $message }}</p>
@enderror
@error('answers.' . $field->id . '.*')
    <p class="cb-error" role="alert">{{ $message }}</p>
@enderror
@error('uploads.' . $field->id)
    <p class="cb-error" role="alert">{{ $message }}</p>
@enderror
