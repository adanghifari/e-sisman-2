<?php

namespace App\Livewire\Settings\TwoFactor;

use Exception;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Component;

class RecoveryCodes extends Component
{
    /** @var list<string> */
    public array $recoveryCodes = [];

    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    public function render()
    {
        return view('livewire.settings.two-factor.recovery-codes');
    }

    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?: [];
            } catch (Exception $exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        }
    }
}
