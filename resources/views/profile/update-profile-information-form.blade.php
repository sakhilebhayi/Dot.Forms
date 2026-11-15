<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        {{ __('Profile Information') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Update your account\'s profile information and email address.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Profile Photo -->
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}">
                <!-- Profile Photo File Input -->
                <input type="file" id="photo" style="display: none;"
                            wire:model.live="photo"
                            x-ref="photo"
                            x-on:change="
                                    photoName = $refs.photo.files[0].name;
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        photoPreview = e.target.result;
                                    };
                                    reader.readAsDataURL($refs.photo.files[0]);
                            " />

                <x-label for="photo" value="{{ __('Photo') }}" />

                <!-- Current Profile Photo -->
                <div x-show="! photoPreview" style="margin-top: 12px;">
                    <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}" style="height: 80px; width: 80px; border-radius: 50%; object-fit: cover;">
                </div>

                <!-- New Profile Photo Preview -->
                <div x-show="photoPreview" style="display: none; margin-top: 12px;">
                    <span style="display: block; height: 80px; width: 80px; border-radius: 50%; background-size: cover; background-position: center;"
                          x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                    </span>
                </div>

                <div style="display: flex; gap: 8px; margin-top: 12px;">
                    <x-secondary-button type="button" x-on:click.prevent="$refs.photo.click()">
                        {{ __('Select A New Photo') }}
                    </x-secondary-button>

                    @if ($this->user->profile_photo_path)
                        <x-secondary-button type="button" wire:click="deleteProfilePhoto">
                            {{ __('Remove Photo') }}
                        </x-secondary-button>
                    @endif
                </div>

                <x-input-error for="photo" />
            </div>
        @endif

        <!-- Name -->
        <div>
            <x-label for="name" value="{{ __('Name') }}" />
            <x-input id="name" type="text" wire:model="state.name" required autocomplete="name" />
            <x-input-error for="name" />
        </div>

        <!-- Email -->
        <div>
            <x-label for="email" value="{{ __('Email') }}" />
            <x-input id="email" type="email" wire:model="state.email" required autocomplete="username" />
            <x-input-error for="email" />

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                <div style="margin-top: 12px; font-size: 13px; color: #6B7280;">
                    {{ __('Your email address is unverified.') }}

                    <button type="button" style="margin-left: 4px; background: none; border: none; color: var(--yellow-dark); cursor: pointer; text-decoration: underline; font-weight: 500; font-family: var(--dot-font-sans);" wire:click.prevent="sendEmailVerification">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </div>

                @if ($this->verificationLinkSent)
                    <div style="margin-top: 12px; font-size: 13px; color: #059669; font-weight: 500;">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </div>
                @endif
            @endif
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button wire:loading.attr="disabled" wire:target="photo">
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
