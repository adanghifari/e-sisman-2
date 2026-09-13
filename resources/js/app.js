require('./bootstrap');

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

(() => {
    const closePicker = (root) => {
        root?.querySelector('[data-user-search-panel]')?.classList.add('hidden');
        root?.querySelector('[data-user-search-trigger]')?.setAttribute('aria-expanded', 'false');
    };

    const openPicker = (root) => {
        document.querySelectorAll('[data-user-search-select]').forEach((picker) => {
            if (picker !== root) {
                closePicker(picker);
            }
        });

        root?.querySelector('[data-user-search-panel]')?.classList.remove('hidden');
        root?.querySelector('[data-user-search-trigger]')?.setAttribute('aria-expanded', 'true');

        const input = root?.querySelector('[data-user-search-input]');
        input?.focus();
        input?.select();
    };

    window.clearUserSearchSelect = (root) => {
        if (!root) {
            return;
        }

        const value = root.querySelector('[data-user-search-value]');
        const initials = root.querySelector('[data-user-search-initials]');
        const name = root.querySelector('[data-user-search-name]');
        const meta = root.querySelector('[data-user-search-meta]');

        if (value) {
            value.value = '';
        }

        if (initials) {
            initials.textContent = '?';
            initials.className = 'grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 ring-1 ring-slate-200';
        }

        if (name) {
            name.textContent = root.dataset.placeholder || 'Pilih user';
        }

        if (meta) {
            meta.textContent = '';
            meta.classList.add('hidden');
        }
    };

    window.setUserSearchSelect = (root, user) => {
        if (!root || !user) {
            return;
        }

        const value = root.querySelector('[data-user-search-value]');
        const initials = root.querySelector('[data-user-search-initials]');
        const name = root.querySelector('[data-user-search-name]');
        const meta = root.querySelector('[data-user-search-meta]');

        if (value) {
            value.value = user.value || user.id || '';
        }

        if (initials) {
            initials.textContent = user.initials || '?';
            initials.className = 'grid size-8 shrink-0 place-items-center rounded-full bg-sky-50 text-xs font-bold text-sky-700 ring-1 ring-sky-100';
        }

        if (name) {
            name.textContent = user.name || root.dataset.placeholder || 'Pilih user';
        }

        if (meta) {
            meta.textContent = user.meta || user.title || user.email || '';
            meta.classList.toggle('hidden', !meta.textContent);
        }
    };

    const syncPlaceholder = (root) => {
        if (!root || root.dataset.placeholder) {
            return;
        }

        root.dataset.placeholder = root.querySelector('[data-user-search-name]')?.textContent || 'Pilih user';
    };

    window.initUserSearchSelect = (root) => {
        syncPlaceholder(root);
    };

    const initUserSearchSelects = () => {
        document.querySelectorAll('[data-user-search-select]').forEach(window.initUserSearchSelect);
    };

    const handleUserSearchClick = (event) => {
        const trigger = event.target.closest('[data-user-search-trigger]');

        if (trigger) {
            const root = trigger.closest('[data-user-search-select]');
            const panel = root?.querySelector('[data-user-search-panel]');

            syncPlaceholder(root);

            if (panel?.classList.contains('hidden')) {
                openPicker(root);
            } else {
                closePicker(root);
            }

            event.preventDefault();
            return;
        }

        const option = event.target.closest('[data-user-search-option]');

        if (option) {
            const root = option.closest('[data-user-search-select]');
            const value = root?.querySelector('[data-user-search-value]');

            syncPlaceholder(root);
            window.setUserSearchSelect(root, option.dataset);

            if (value) {
                value.dispatchEvent(new Event('input', { bubbles: true }));
                value.dispatchEvent(new Event('change', { bubbles: true }));
            }

            closePicker(root);

            root?.dispatchEvent(new CustomEvent('user-search-select:selected', {
                bubbles: true,
                detail: { ...option.dataset },
            }));

            if (root?.dataset.clearOnSelect === 'true') {
                window.clearUserSearchSelect(root);
            }

            event.preventDefault();
            return;
        }

        document.querySelectorAll('[data-user-search-select]').forEach((root) => {
            if (!root.contains(event.target)) {
                closePicker(root);
            }
        });
    };

    const handleUserSearchInput = (event) => {
        const input = event.target.closest('[data-user-search-input]');

        if (!input) {
            return;
        }

        const root = input.closest('[data-user-search-select]');
        const query = input.value.trim().toLowerCase();
        let visibleCount = 0;

        root?.querySelectorAll('[data-user-search-option]').forEach((option) => {
            const isVisible = (option.dataset.search || '').includes(query);
            option.classList.toggle('hidden', !isVisible);
            visibleCount += isVisible ? 1 : 0;
        });

        root?.querySelector('[data-user-search-empty]')?.classList.toggle('hidden', visibleCount > 0);
    };

    const handleUserSearchKeydown = (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('[data-user-search-select]').forEach(closePicker);
        }
    };

    document.addEventListener('click', handleUserSearchClick);
    document.addEventListener('input', handleUserSearchInput);
    document.addEventListener('keydown', handleUserSearchKeydown);
    document.addEventListener('DOMContentLoaded', initUserSearchSelects);
    document.addEventListener('livewire:navigated', initUserSearchSelects);

    initUserSearchSelects();
})();
