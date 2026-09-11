<x-layouts.app :title="$title">
    <div class="space-y-6">
        <x-ui.page-header :title="$title" description="Modul ini sedang dalam proses rewrite ke Laravel 8 dan Livewire 2." />
        <x-ui.panel>
            <p class="text-sm leading-6 text-slate-600">
                Halaman ini sudah disiapkan agar navigasi dan permission tetap stabil selama proses downgrade. Implementasi final akan dipindahkan dari repo lama secara bertahap dan disesuaikan tanpa Flux/Livewire versi baru.
            </p>
        </x-ui.panel>
    </div>
</x-layouts.app>