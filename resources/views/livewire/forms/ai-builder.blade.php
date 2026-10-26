<div style="padding: 32px 24px; background: var(--bg);">
    <div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 28px;">
        <!-- Header -->
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h1 style="font-family: 'Sora', sans-serif; font-size: 32px; font-weight: 800; color: var(--ink); margin: 0 0 8px 0;">
                    ✨ AI Form Builder
                </h1>
                <p style="font-size: 14px; color: var(--muted); margin: 0;">
                    Describe your form and generate a complete draft instantly.
                </p>
            </div>
            <a href="{{ route('teams.forms', $team) }}" style="padding: 11px 18px; background: white; border: 1px solid #E5E5E5; border-radius: 8px; font-size: 14px; font-weight: 600; color: var(--ink); text-decoration: none; cursor: pointer; transition: all 0.2s;">
                ← Back to Forms
            </a>
        </div>

        <!-- Prompt Section -->
        <div style="background: white; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 14px;">
            <label for="ab-describe-the-form-you-want" style="display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--muted); letter-spacing: 0.5px;">
                Describe the form you want
            </label>
            <textarea id="ab-describe-the-form-you-want" wire:model="prompt" rows="5" style="width: 100%; padding: 12px 14px; border: 1px solid #E5E5E5; border-radius: 8px; font-size: 14px; font-family: 'Inter', sans-serif; resize: vertical;" placeholder="Event registration with name, email, dietary restrictions..."></textarea>
            <button wire:click="generate" style="padding: 12px 18px; background: var(--yellow); color: var(--ink); border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; align-self: flex-start;">
                ✨ Generate Form Blueprint
            </button>
        </div>

        @if ($generated)
            <!-- Generated Form Section -->
            <div style="background: white; border: 1px solid #E5E5E5; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 18px;">
                <!-- Form Details -->
                <div style="display: flex; flex-direction: column; gap: 14px; padding-bottom: 18px; border-bottom: 1px solid #E5E5E5;">
                    <div>
                        <label for="ab-form-title" style="display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; letter-spacing: 0.5px;">
                            Form Title
                        </label>
                        <input id="ab-form-title" type="text" wire:model="title" style="width: 100%; padding: 11px 14px; border: 1px solid #E5E5E5; border-radius: 8px; font-size: 14px; font-family: 'Inter', sans-serif;" />
                    </div>

                    <div>
                        <label for="ab-description" style="display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; letter-spacing: 0.5px;">
                            Description
                        </label>
                        <textarea id="ab-description" wire:model="description" rows="3" style="width: 100%; padding: 11px 14px; border: 1px solid #E5E5E5; border-radius: 8px; font-size: 14px; font-family: 'Inter', sans-serif; resize: vertical;"></textarea>
                    </div>
                </div>

                <!-- Fields Section -->
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <h3 style="font-family: 'Sora', sans-serif; font-size: 15px; font-weight: 700; color: var(--ink); margin: 0;">
                            Generated Fields
                        </h3>
                        <button wire:click="addField" style="padding: 8px 12px; background: var(--yellow); color: var(--ink); border: none; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                            + Add Field
                        </button>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        @foreach ($fields as $index => $field)
                            <article style="padding: 14px; background: var(--bg); border: 1px solid #E5E5E5; border-radius: 10px; display: flex; flex-direction: column; gap: 12px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div>
                                        <label for="ab-label-{{ $index }}" style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; letter-spacing: 0.5px;">
                                            Label
                                        </label>
                                        <input id="ab-label-{{ $index }}" type="text" wire:model="fields.{{ $index }}.label" style="width: 100%; padding: 10px 12px; border: 1px solid #E5E5E5; border-radius: 6px; font-size: 13px; font-family: 'Inter', sans-serif;" />
                                    </div>
                                    <div>
                                        <label for="ab-type-{{ $index }}" style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; letter-spacing: 0.5px;">
                                            Type
                                        </label>
                                        <select id="ab-type-{{ $index }}" wire:model="fields.{{ $index }}.type" style="width: 100%; padding: 10px 12px; border: 1px solid #E5E5E5; border-radius: 6px; font-size: 13px; font-family: 'Inter', sans-serif;">
                                            @foreach (['text', 'email', 'number', 'textarea', 'select', 'radio', 'checkbox', 'date', 'file'] as $type)
                                                <option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label for="ab-placeholder-{{ $index }}" style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; letter-spacing: 0.5px;">
                                        Placeholder
                                    </label>
                                    <input id="ab-placeholder-{{ $index }}" type="text" wire:model="fields.{{ $index }}.placeholder" style="width: 100%; padding: 10px 12px; border: 1px solid #E5E5E5; border-radius: 6px; font-size: 13px; font-family: 'Inter', sans-serif;" />
                                </div>

                                <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 8px; border-top: 1px solid #E5E5E5;">
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--ink); cursor: pointer;">
                                        <input type="checkbox" wire:model="fields.{{ $index }}.required" style="width: 16px; height: 16px; cursor: pointer; accent-color: var(--yellow);" />
                                        <span>Mark as required</span>
                                    </label>
                                    <button wire:click="removeField({{ $index }})" style="padding: 8px 12px; background: var(--red-light); color: var(--red); border: 1px solid #FFCDD2; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                        🗑️ Remove
                                    </button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                <!-- Save Button -->
                <button wire:click="saveForm" style="padding: 12px 18px; background: var(--yellow); color: var(--ink); border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; align-self: flex-start;">
                    💾 Save Generated Form
                </button>
            </div>
        @endif
    </div>
</div>
