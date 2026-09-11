<?php

namespace App\Livewire\Administration\AccessMenu;

use App\Models\Permission;
use App\Support\Access\PermissionCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $module = '';

    public function getPermissionBundlesProperty(): Collection
    {
        return app(PermissionCatalog::class)
            ->bundles($this->module ?: null, $this->search ?: null)
            ->values();
    }

    public function render(): View
    {
        $totalPermissions = Permission::query()
            ->when($this->module !== '', fn ($query) => $query->where('module', $this->module))
            ->count();

        $modules = app(PermissionCatalog::class)->modules();

        return view('livewire.administration.access-menus.index', [
            'permissionBundles' => $this->permissionBundles,
            'moduleOptions' => $modules,
            'totalPermissions' => $totalPermissions,
        ])->layout('components.layouts.app', ['title' => 'Menu Akses']);
    }
}
