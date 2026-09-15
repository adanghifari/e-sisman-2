<?php

namespace Tests\Feature\View;

use App\Support\Icons;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class IconComponentTest extends TestCase
{
    public function test_icons_class_contains_authentic_heroicons(): void
    {
        $this->assertTrue(Icons::has('pencil'));
        $this->assertTrue(Icons::has('arrow-path'));
        $this->assertTrue(Icons::has('cloud-arrow-up'));
        $this->assertTrue(Icons::has('link'));
        $this->assertTrue(Icons::has('trash'));

        // Check arrow-path path is authentic and does not have corrupted A9 9 curve
        $arrowPath = Icons::get('arrow-path');
        $this->assertStringContainsString('a8.25 8.25', $arrowPath);
        $this->assertStringNotContainsString('A9 9', $arrowPath);
    }

    public function test_flux_icon_component_renders_pencil_icon(): void
    {
        $rendered = Blade::render('<x-flux.icon name="pencil" class="size-4" />');

        $this->assertStringContainsString('<svg', $rendered);
        $this->assertStringContainsString('size-4', $rendered);
        $this->assertStringContainsString('inline-block shrink-0', $rendered);
        $this->assertStringContainsString('m16.862 4.487', $rendered);
        $this->assertStringContainsString('<title>pencil</title>', $rendered);
    }

    public function test_flux_icon_component_renders_authentic_arrow_path(): void
    {
        $rendered = Blade::render('<x-flux.icon name="arrow-path" class="size-6" />');

        $this->assertStringContainsString('<svg', $rendered);
        $this->assertStringContainsString('size-6', $rendered);
        $this->assertStringContainsString('inline-block shrink-0', $rendered);
        $this->assertStringContainsString('a8.25 8.25 0 0 0 13.803-3.7', $rendered);
    }

    public function test_generic_icon_component_delegates_properly(): void
    {
        $rendered = Blade::render('<x-icon name="pencil" class="size-5 text-sky-700" />');

        $this->assertStringContainsString('<svg', $rendered);
        $this->assertStringContainsString('m16.862 4.487', $rendered);
        $this->assertStringContainsString('text-sky-700', $rendered);
    }

    public function test_icon_button_component_renders_pencil_for_edit_actions(): void
    {
        $rendered = Blade::render('<x-ui.icon-button icon="pencil" label="Edit tahap approval" size="sm" />');

        $this->assertStringContainsString('<button', $rendered);
        $this->assertStringContainsString('aria-label="Edit tahap approval"', $rendered);
        $this->assertStringContainsString('m16.862 4.487', $rendered);
    }

    public function test_sidebar_toggle_renders_open_and_closed_icons(): void
    {
        $rendered = Blade::render('
            <button type="button" class="sidebar-toggle" data-sidebar-toggle>
                <x-flux.icon name="chevron-left" class="sidebar-icon-open size-4 text-white" />
                <x-flux.icon name="chevron-right" class="sidebar-icon-closed size-4 text-white" />
            </button>
        ');

        $this->assertStringContainsString('sidebar-icon-open', $rendered);
        $this->assertStringContainsString('sidebar-icon-closed', $rendered);
        // Chevron left path
        $this->assertStringContainsString('M15.75 19.5 8.25 12l7.5-7.5', $rendered);
        // Chevron right path
        $this->assertStringContainsString('m8.25 4.5 7.5 7.5-7.5 7.5', $rendered);
    }

    public function test_mobile_toggle_renders_bars_icon(): void
    {
        $rendered = Blade::render('
            <button type="button" data-mobile-nav-toggle>
                <x-flux.icon name="bars-3" class="size-6 text-white" />
            </button>
        ');

        $this->assertStringContainsString('<svg', $rendered);
        $this->assertStringContainsString('M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5', $rendered);
    }
}