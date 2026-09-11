@props([
    'label',
    'name',
    'options',
    'selected' => null,
    'placeholder' => '-Pilih-',
    'selectedPlaceholder' => null,
    'errorName' => null,
    'required' => false,
])

@php
    $selected ??= old($name, []);
    $selected = collect((array) $selected)->map(fn ($value) => (string) $value)->all();
    $errorName ??= $name;
    $inputName = str($name)->endsWith('[]') ? $name : "{$name}[]";
    $selectedPlaceholder ??= "Tambah {$label}";
@endphp

<div
    class="block"
    data-multi-select
    data-multi-select-input-name="{{ $inputName }}"
    data-multi-select-placeholder="{{ $placeholder }}"
    data-multi-select-selected-placeholder="{{ $selectedPlaceholder }}"
    data-multi-select-required="{{ $required ? 'true' : 'false' }}"
>
    <span class="mb-2 block text-base font-medium text-slate-500">{{ $label }}</span>

    <div class="mb-2 hidden flex-col items-start gap-2" data-multi-select-chip-list>
        @foreach ($options as $option)
            @php
                $optionValue = (string) $option['value'];
            @endphp

            @if (in_array($optionValue, $selected, true))
                <span class="inline-flex max-w-full items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-semibold text-slate-700" data-multi-select-chip="{{ $optionValue }}">
                    <input type="hidden" name="{{ $inputName }}" value="{{ $optionValue }}">
                    <span class="max-w-40 truncate">{{ $option['label'] }}</span>
                    <button type="button" class="grid size-6 shrink-0 place-items-center rounded-full bg-blue-500 text-xs font-bold text-white transition hover:bg-blue-600" data-multi-select-remove aria-label="Hapus {{ $option['label'] }}">x</button>
                </span>
            @endif
        @endforeach
    </div>

    <select
        @if ($required) required @endif
        class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
        data-multi-select-input
    >
        <option value="" data-multi-select-placeholder>{{ $placeholder }}</option>
        @foreach ($options as $option)
            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
        @endforeach
    </select>

    @error($errorName)
        <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
    @enderror
</div>

@once
    <script>
        (() => {
            const createMultiSelectChip = (picker, value, label) => {
                const chipList = picker.querySelector('[data-multi-select-chip-list]');
                const inputName = picker.dataset.multiSelectInputName;

                if (!chipList || !inputName || !value || chipList.querySelector(`[data-multi-select-chip="${value}"]`)) {
                    return;
                }

                const chip = document.createElement('span');
                chip.className = 'inline-flex max-w-full items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-semibold text-slate-700';
                chip.dataset.multiSelectChip = value;

                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = inputName;
                hidden.value = value;

                const text = document.createElement('span');
                text.className = 'max-w-40 truncate';
                text.textContent = label;

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'grid size-6 shrink-0 place-items-center rounded-full bg-blue-500 text-xs font-bold text-white transition hover:bg-blue-600';
                removeButton.dataset.multiSelectRemove = '';
                removeButton.setAttribute('aria-label', `Hapus ${label}`);
                removeButton.textContent = 'x';

                chip.append(hidden, text, removeButton);
                chipList.append(chip);
            };

            const syncMultiSelect = (picker) => {
                const chipList = picker?.querySelector('[data-multi-select-chip-list]');
                const placeholder = picker?.querySelector('[data-multi-select-placeholder]');
                const select = picker?.querySelector('[data-multi-select-input]');
                const hasChips = (chipList?.querySelectorAll('[data-multi-select-chip]').length ?? 0) > 0;

                if (placeholder) {
                    placeholder.textContent = hasChips
                        ? picker.dataset.multiSelectSelectedPlaceholder
                        : picker.dataset.multiSelectPlaceholder;
                }

                if (select) {
                    select.required = picker.dataset.multiSelectRequired === 'true' && !hasChips;
                }

                if (chipList) {
                    chipList.classList.toggle('hidden', !hasChips);
                    chipList.classList.toggle('flex', hasChips);
                }
            };

            window.initMultiSelect = (picker) => {
                if (!picker || picker.dataset.multiSelectInitialized === 'true') {
                    return;
                }

                picker.dataset.multiSelectInitialized = 'true';
                syncMultiSelect(picker);
            };

            const handleMultiSelectChange = (event) => {
                const select = event.target.closest('[data-multi-select-input]');

                if (!select || !select.value) {
                    return;
                }

                const picker = select.closest('[data-multi-select]');
                const option = select.selectedOptions[0];

                createMultiSelectChip(picker, option.value, option.textContent.trim());
                select.value = '';
                syncMultiSelect(picker);
            };

            const handleMultiSelectClick = (event) => {
                const removeButton = event.target.closest('[data-multi-select-remove]');

                if (!removeButton) {
                    return;
                }

                const picker = removeButton.closest('[data-multi-select]');
                removeButton.closest('[data-multi-select-chip]')?.remove();
                syncMultiSelect(picker);
            };

            const initMultiSelects = () => {
                document.querySelectorAll('[data-multi-select]').forEach(window.initMultiSelect);
            };

            document.removeEventListener('change', window.handleMultiSelectChange);
            document.removeEventListener('click', window.handleMultiSelectClick);
            document.removeEventListener('lived', window.initMultiSelects);

            window.handleMultiSelectChange = handleMultiSelectChange;
            window.handleMultiSelectClick = handleMultiSelectClick;
            window.initMultiSelects = initMultiSelects;

            document.addEventListener('change', window.handleMultiSelectChange);
            document.addEventListener('click', window.handleMultiSelectClick);
            document.addEventListener('lived', window.initMultiSelects);

            window.initMultiSelects();
        })();
    </script>
@endonce
