<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortedUiRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_ported_ui_routes_render_for_super_admin(): void
    {
        $this->seed();

        $user = User::where('nik', '000001')->firstOrFail();

        $routes = [
            route('dashboard'),
            route('documents.inbox'),
            route('documents.create'),
            route('documents.create.drafts'),
            route('documents.master'),
            route('documents.obsolete'),
            route('document-templates.index'),
            route('reports.index'),
            route('activity-log.index'),
            route('users.index'),
            route('access-groups.index'),
            route('access-menus.index'),
            route('approval-flows.index'),
            route('master-data.process-functions'),
            route('master-data.business-processes'),
            route('master-data.departments'),
            route('master-data.document-types'),
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)
                ->get($route)
                ->assertOk();
        }
    }
}