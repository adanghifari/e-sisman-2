<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Appearance')" :subheading="__('Use the browser and operating system appearance for this Laravel 8 build')">
        <x-ui.panel :title="__('Appearance')" :description="__('The downgraded stack does not include the Flux appearance switcher from the old project.')">
            <p class="text-sm text-slate-600">
                {{ __('The application currently uses the standard light interface from the E-SISMAN layout.') }}
            </p>
        </x-ui.panel>
    </x-settings.layout>
</section>
