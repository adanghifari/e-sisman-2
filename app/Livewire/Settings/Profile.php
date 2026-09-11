<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Profile extends Component
{
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public bool $saved = false;

    public bool $verificationLinkSent = false;

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->saved = true;
        $this->verificationLinkSent = false;
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            redirect()->route('dashboard');

            return;
        }

        $user->sendEmailVerificationNotification();

        $this->verificationLinkSent = true;
    }

    public function getHasUnverifiedEmailProperty(): bool
    {
        $user = Auth::user();

        return $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail();
    }

    public function getShowDeleteUserProperty(): bool
    {
        $user = Auth::user();

        return ! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail();
    }

    public function render(): View
    {
        return view('livewire.settings.profile')
            ->layout('components.layouts.app', ['title' => 'Profile']);
    }
}
