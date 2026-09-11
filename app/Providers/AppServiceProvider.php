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
        if (! function_exists('str')) {
            require_once __DIR__.'/../Support/helpers.php';
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        \Illuminate\Support\Facades\Blade::directive('checked', function ($expression) {
            return "<?php if ({$expression}): echo 'checked'; endif; ?>";
        });
        \Illuminate\Support\Facades\Blade::directive('selected', function ($expression) {
            return "<?php if ({$expression}): echo 'selected'; endif; ?>";
        });
        \Illuminate\Support\Facades\Blade::directive('disabled', function ($expression) {
            return "<?php if ({$expression}): echo 'disabled'; endif; ?>";
        });

        \Illuminate\Http\Request::macro('string', function ($key, $default = null) {
            return \Illuminate\Support\Str::of($this->input($key, $default));
        });
        \Illuminate\Http\Request::macro('boolean', function ($key, $default = false) {
            return filter_var($this->input($key, $default), FILTER_VALIDATE_BOOLEAN);
        });
        \Illuminate\Http\Request::macro('date', function ($key, $format = null, $tz = null) {
            if ($this->isNotFilled($key)) {
                return null;
            }

            return \Illuminate\Support\Carbon::parse($this->input($key), $tz);
        });

        \Illuminate\Support\Stringable::macro('value', function () {
            return (string) $this->value;
        });
        \Illuminate\Support\Stringable::macro('toString', function () {
            return (string) $this->value;
        });

        Livewire::component('settings.delete-user-form', DeleteUserForm::class);
        Livewire::component('settings.two-factor.recovery-codes', RecoveryCodes::class);
    }
}
