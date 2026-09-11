<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Security')" :subheading="__('Update your password and two-factor authentication settings')">
        <div class="space-y-6">
            <x-ui.panel :title="__('Update password')" :description="__('Ensure your account is using a long, random password to stay secure')">
                <form wire:submit.prevent="updatePassword" class="space-y-5">
                    @if ($passwordUpdated)
                        <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                            {{ __('Password updated.') }}
                        </div>
                    @endif

                    <x-ui.form-input
                        :label="__('Current password')"
                        name="current_password"
                        type="password"
                        wire:model.defer="current_password"
                        autocomplete="current-password"
                    />

                    <x-ui.form-input
                        :label="__('New password')"
                        name="password"
                        type="password"
                        wire:model.defer="password"
                        autocomplete="new-password"
                    />

                    <x-ui.form-input
                        :label="__('Confirm password')"
                        name="password_confirmation"
                        type="password"
                        wire:model.defer="password_confirmation"
                        autocomplete="new-password"
                    />

                    <x-ui.action-button type="submit">
                        {{ __('Save') }}
                    </x-ui.action-button>
                </form>
            </x-ui.panel>

            <x-ui.panel :title="__('Two-factor authentication')" :description="__('Add additional security to your account using an authenticator app')">
                @if ($canManageTwoFactor)
                    <div class="space-y-4">
                        <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            {{ $twoFactorEnabled ? __('Two-factor authentication is enabled.') : __('Two-factor authentication is not enabled yet.') }}
                        </div>

                        @if ($twoFactorEnabled)
                            <livewire:settings.two-factor.recovery-codes />

                            <button type="button" wire:click="disable" class="inline-flex h-10 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                                {{ __('Disable') }}
                            </button>
                        @else
                            <x-ui.action-button type="button" wire:click="enable">
                                {{ __('Enable') }}
                            </x-ui.action-button>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-slate-600">{{ __('Two-factor authentication is not enabled in Fortify configuration.') }}</p>
                @endif
            </x-ui.panel>
        </div>

        @if ($showModal)
            <x-ui.modal :title="$this->modalConfig['title']" :description="$this->modalConfig['description']" close-action="closeModal" max-width="lg">
                <div class="space-y-5 px-6 py-5">
                    @error('setupData')
                        <div class="rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                            {{ $message }}
                        </div>
                    @enderror

                    @if (! $showVerificationStep)
                        @if ($qrCodeSvg)
                            <div class="flex justify-center rounded-lg border border-slate-100 bg-white p-4">
                                {!! $qrCodeSvg !!}
                            </div>
                        @endif

                        @if ($manualSetupKey)
                            <div>
                                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Setup key') }}</p>
                                <code class="block rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">{{ $manualSetupKey }}</code>
                            </div>
                        @endif
                    @else
                        <x-ui.form-input
                            :label="__('Authentication code')"
                            name="code"
                            wire:model.defer="code"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                        />
                    @endif
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    @if ($showVerificationStep)
                        <x-ui.action-button type="button" variant="secondary" wire:click="resetVerification">
                            {{ __('Back') }}
                        </x-ui.action-button>

                        <x-ui.action-button type="button" wire:click="confirmTwoFactor">
                            {{ $this->modalConfig['buttonText'] }}
                        </x-ui.action-button>
                    @else
                        <x-ui.action-button type="button" wire:click="showVerificationIfNecessary">
                            {{ $this->modalConfig['buttonText'] }}
                        </x-ui.action-button>
                    @endif
                </div>
            </x-ui.modal>
        @endif
    </x-settings.layout>
</section>
