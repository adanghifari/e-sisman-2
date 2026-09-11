<section class="mt-6">
    <x-ui.panel :title="__('Delete account')" :description="__('Delete your account and all of its resources')">
        <p class="mb-4 text-sm text-slate-600">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted.') }}
        </p>

        <button type="button" wire:click="confirmDeletion" class="inline-flex h-10 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
            {{ __('Delete account') }}
        </button>
    </x-ui.panel>

    @if ($confirmingDeletion)
        <x-ui.modal :title="__('Are you sure you want to delete your account?')" :description="__('Please enter your password to confirm account deletion.')" close-action="cancelDeletion" max-width="lg">
            <form wire:submit.prevent="deleteUser" class="space-y-5 px-6 py-5">
                <x-ui.form-input
                    :label="__('Password')"
                    name="password"
                    type="password"
                    wire:model.defer="password"
                    autocomplete="current-password"
                />

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-5">
                    <x-ui.action-button type="button" variant="secondary" wire:click="cancelDeletion">
                        {{ __('Cancel') }}
                    </x-ui.action-button>

                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                        {{ __('Delete account') }}
                    </button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</section>
