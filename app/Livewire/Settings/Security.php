<?php

namespace App\Livewire\Settings;

use App\Concerns\PasswordValidationRules;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Component;

class Security extends Component
{
    use PasswordValidationRules;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $passwordUpdated = false;

    public bool $canManageTwoFactor = false;

    public bool $twoFactorEnabled = false;

    public bool $requiresConfirmation = false;

    public string $qrCodeSvg = '';

    public string $manualSetupKey = '';

    public bool $showModal = false;

    public bool $showVerificationStep = false;

    public string $code = '';

    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if (! $this->canManageTwoFactor) {
            return;
        }

        if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
            $disableTwoFactorAuthentication(auth()->user());
        }

        $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->passwordUpdated = true;
    }

    public function enable(EnableTwoFactorAuthentication $enableTwoFactorAuthentication): void
    {
        abort_unless($this->canManageTwoFactor, 404);

        $enableTwoFactorAuthentication(auth()->user());

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        }

        $this->loadSetupData();

        $this->showModal = true;
    }

    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;
            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
    }

    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->closeModal();

        $this->twoFactorEnabled = true;
    }

    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');
        $this->resetErrorBag();
    }

    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        abort_unless($this->canManageTwoFactor, 404);

        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }

    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showModal',
            'showVerificationStep',
        );

        $this->resetErrorBag();

        if ($this->canManageTwoFactor && ! $this->requiresConfirmation) {
            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        }
    }

    public function getModalConfigProperty(): array
    {
        if ($this->twoFactorEnabled) {
            return [
                'title' => __('Two-factor authentication enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify authentication code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable two-factor authentication'),
            'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
            'buttonText' => __('Continue'),
        ];
    }

    public function render(): View
    {
        return view('livewire.settings.security')
            ->layout('components.layouts.app', ['title' => 'Security']);
    }

    private function loadSetupData(): void
    {
        $user = auth()->user();

        try {
            $this->qrCodeSvg = $user?->twoFactorQrCodeSvg() ?? '';
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception $exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }
}
