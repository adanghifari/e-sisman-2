<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <x-ui.panel>
            <form wire:submit.prevent="updateProfileInformation" class="space-y-5">
                @if ($saved)
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        {{ __('Profile updated.') }}
                    </div>
                @endif

                <x-ui.form-input
                    :label="__('Name')"
                    name="name"
                    wire:model.defer="name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <div>
                    <x-ui.form-input
                        :label="__('Email')"
                        name="email"
                        type="email"
                        wire:model.defer="email"
                        required
                        autocomplete="email"
                    />

                    @if ($this->hasUnverifiedEmail)
                        <div class="mt-3 rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                            <p>{{ __('Your email address is unverified.') }}</p>
                            <button type="button" class="mt-2 font-semibold text-amber-800 underline" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </button>

                            @if ($verificationLinkSent)
                                <p class="mt-2 font-semibold">{{ __('A new verification link has been sent to your email address.') }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                <div>
                    <x-ui.action-button type="submit">
                        {{ __('Save') }}
                    </x-ui.action-button>
                </div>
            </form>
        </x-ui.panel>

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
