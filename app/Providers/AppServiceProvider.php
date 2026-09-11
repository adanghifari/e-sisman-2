<?php

namespace App\Providers;

use App\Livewire\Settings\DeleteUserForm;
use App\Livewire\Settings\TwoFactor\RecoveryCodes;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Livewire::component('settings.delete-user-form', DeleteUserForm::class);
        Livewire::component('settings.two-factor.recovery-codes', RecoveryCodes::class);
    }
}
