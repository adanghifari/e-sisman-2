<?php

namespace App\Livewire\Settings;

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    public bool $confirmingDeletion = false;

    public function confirmDeletion(): void
    {
        $this->resetErrorBag();
        $this->password = '';
        $this->confirmingDeletion = true;
    }

    public function cancelDeletion(): void
    {
        $this->resetErrorBag();
        $this->password = '';
        $this->confirmingDeletion = false;
    }

    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = Auth::user();

        $logout();

        $user->delete();

        redirect('/');
    }

    public function render()
    {
        return view('livewire.settings.delete-user-form');
    }
}
