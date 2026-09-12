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

        $livewireAliases = [
            // Master Data - Business Function (Proses / Fungsi)
            'master-data.process-functions' => \App\Livewire\MasterData\BusinessFunction\Index::class,
            'master-data.process-functions.index' => \App\Livewire\MasterData\BusinessFunction\Index::class,
            'master-data.process-function' => \App\Livewire\MasterData\BusinessFunction\Index::class,
            'master-data.process-function.index' => \App\Livewire\MasterData\BusinessFunction\Index::class,
            'master-data.business-functions' => \App\Livewire\MasterData\BusinessFunction\Index::class,
            'master-data.business-functions.index' => \App\Livewire\MasterData\BusinessFunction\Index::class,
            'master-data.business-function' => \App\Livewire\MasterData\BusinessFunction\Index::class,
            'master-data.business-function.index' => \App\Livewire\MasterData\BusinessFunction\Index::class,

            // Master Data - Business Process
            'master-data.business-processes' => \App\Livewire\MasterData\BusinessProcess\Index::class,
            'master-data.business-processes.index' => \App\Livewire\MasterData\BusinessProcess\Index::class,
            'master-data.business-process' => \App\Livewire\MasterData\BusinessProcess\Index::class,
            'master-data.business-process.index' => \App\Livewire\MasterData\BusinessProcess\Index::class,

            // Master Data - Department
            'master-data.departments' => \App\Livewire\MasterData\Department\Index::class,
            'master-data.departments.index' => \App\Livewire\MasterData\Department\Index::class,
            'master-data.department' => \App\Livewire\MasterData\Department\Index::class,
            'master-data.department.index' => \App\Livewire\MasterData\Department\Index::class,

            // Master Data - Document Type
            'master-data.document-types' => \App\Livewire\MasterData\DocumentType\Index::class,
            'master-data.document-types.index' => \App\Livewire\MasterData\DocumentType\Index::class,
            'master-data.document-type' => \App\Livewire\MasterData\DocumentType\Index::class,
            'master-data.document-type.index' => \App\Livewire\MasterData\DocumentType\Index::class,

            // Administration - Access Group
            'administration.access-groups' => \App\Livewire\Administration\AccessGroup\Index::class,
            'administration.access-groups.index' => \App\Livewire\Administration\AccessGroup\Index::class,
            'administration.access-group' => \App\Livewire\Administration\AccessGroup\Index::class,
            'administration.access-group.index' => \App\Livewire\Administration\AccessGroup\Index::class,
            'access-groups' => \App\Livewire\Administration\AccessGroup\Index::class,
            'access-groups.index' => \App\Livewire\Administration\AccessGroup\Index::class,
            'access-group' => \App\Livewire\Administration\AccessGroup\Index::class,
            'access-group.index' => \App\Livewire\Administration\AccessGroup\Index::class,

            // Administration - Access Menu
            'administration.access-menus' => \App\Livewire\Administration\AccessMenu\Index::class,
            'administration.access-menus.index' => \App\Livewire\Administration\AccessMenu\Index::class,
            'administration.access-menu' => \App\Livewire\Administration\AccessMenu\Index::class,
            'administration.access-menu.index' => \App\Livewire\Administration\AccessMenu\Index::class,
            'access-menus' => \App\Livewire\Administration\AccessMenu\Index::class,
            'access-menus.index' => \App\Livewire\Administration\AccessMenu\Index::class,
            'access-menu' => \App\Livewire\Administration\AccessMenu\Index::class,
            'access-menu.index' => \App\Livewire\Administration\AccessMenu\Index::class,

            // Administration - Approval Flow
            'administration.approval-flows' => \App\Livewire\Administration\ApprovalFlow\Index::class,
            'administration.approval-flows.index' => \App\Livewire\Administration\ApprovalFlow\Index::class,
            'administration.approval-flow' => \App\Livewire\Administration\ApprovalFlow\Index::class,
            'administration.approval-flow.index' => \App\Livewire\Administration\ApprovalFlow\Index::class,
            'approval-flows' => \App\Livewire\Administration\ApprovalFlow\Index::class,
            'approval-flows.index' => \App\Livewire\Administration\ApprovalFlow\Index::class,
            'approval-flow' => \App\Livewire\Administration\ApprovalFlow\Index::class,
            'approval-flow.index' => \App\Livewire\Administration\ApprovalFlow\Index::class,

            // Administration - User
            'administration.users' => \App\Livewire\Administration\User\Index::class,
            'administration.users.index' => \App\Livewire\Administration\User\Index::class,
            'administration.user' => \App\Livewire\Administration\User\Index::class,
            'administration.user.index' => \App\Livewire\Administration\User\Index::class,
            'users' => \App\Livewire\Administration\User\Index::class,
            'users.index' => \App\Livewire\Administration\User\Index::class,
            'user' => \App\Livewire\Administration\User\Index::class,
            'user.index' => \App\Livewire\Administration\User\Index::class,

            // Settings
            'settings.appearance' => \App\Livewire\Settings\Appearance::class,
            'settings.appearance.edit' => \App\Livewire\Settings\Appearance::class,
            'appearance.edit' => \App\Livewire\Settings\Appearance::class,
            'appearance' => \App\Livewire\Settings\Appearance::class,
            'settings.profile' => \App\Livewire\Settings\Profile::class,
            'settings.profile.edit' => \App\Livewire\Settings\Profile::class,
            'profile.edit' => \App\Livewire\Settings\Profile::class,
            'profile' => \App\Livewire\Settings\Profile::class,
            'settings.security' => \App\Livewire\Settings\Security::class,
            'settings.security.edit' => \App\Livewire\Settings\Security::class,
            'security.edit' => \App\Livewire\Settings\Security::class,
            'security' => \App\Livewire\Settings\Security::class,
            'settings.delete-user-form' => DeleteUserForm::class,
            'settings.two-factor.recovery-codes' => RecoveryCodes::class,
        ];

        foreach ($livewireAliases as $alias => $class) {
            Livewire::component($alias, $class);
        }

        Livewire::resolveMissingComponent(function (string $alias) use ($livewireAliases) {
            $cleaned = (string) preg_replace('/^(app\.)?(livewire\.)?/', '', $alias);

            if (isset($livewireAliases[$cleaned])) {
                return $livewireAliases[$cleaned];
            }

            if (isset($livewireAliases[$alias])) {
                return $livewireAliases[$alias];
            }

            $finder = app(\Livewire\LivewireComponentsFinder::class);
            if ($found = $finder->find($cleaned)) {
                return $found;
            }

            if ($found = $finder->find($cleaned.'.index')) {
                return $found;
            }

            $segments = array_map(
                fn ($part) => \Illuminate\Support\Str::studly($part),
                explode('.', $cleaned)
            );

            $candidate = 'App\\Livewire\\'.implode('\\', $segments);
            if (class_exists($candidate) && is_subclass_of($candidate, \Livewire\Component::class)) {
                return $candidate;
            }

            $candidateIndex = $candidate.'\\Index';
            if (class_exists($candidateIndex) && is_subclass_of($candidateIndex, \Livewire\Component::class)) {
                return $candidateIndex;
            }

            $singularSegments = array_map(
                fn ($part) => \Illuminate\Support\Str::studly(\Illuminate\Support\Str::singular($part)),
                explode('.', $cleaned)
            );

            $singularCandidate = 'App\\Livewire\\'.implode('\\', $singularSegments);
            if (class_exists($singularCandidate) && is_subclass_of($singularCandidate, \Livewire\Component::class)) {
                return $singularCandidate;
            }

            $singularCandidateIndex = $singularCandidate.'\\Index';
            if (class_exists($singularCandidateIndex) && is_subclass_of($singularCandidateIndex, \Livewire\Component::class)) {
                return $singularCandidateIndex;
            }

            return null;
        });
    }
}
