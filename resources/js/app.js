const bootstrap = window.bootstrap ?? {};

const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const collapsedClass = 'app-sidebar-collapsed';
const storageKey = 'inventory-sidebar-collapsed';

const setSidebarState = (isCollapsed) => {
    document.body.classList.toggle(collapsedClass, isCollapsed);

    if (!sidebarToggle) {
        return;
    }

    const label = isCollapsed ? 'Expandir menú' : 'Contraer menú';
    sidebarToggle.setAttribute('aria-label', label);
    sidebarToggle.setAttribute('title', label);
};

try {
    setSidebarState(window.localStorage.getItem(storageKey) === 'true');
} catch {
    setSidebarState(false);
}

sidebarToggle?.addEventListener('click', () => {
    const isCollapsed = !document.body.classList.contains(collapsedClass);
    setSidebarState(isCollapsed);

    try {
        window.localStorage.setItem(storageKey, String(isCollapsed));
    } catch {
        // Keep the UI working even when storage is unavailable.
    }
});

document.querySelectorAll('#appSidebar .app-nav-link').forEach((link) => {
    link.addEventListener('click', () => {
        if (!window.matchMedia('(max-width: 991.98px)').matches) {
            return;
        }

        const sidebar = document.querySelector('#appSidebar');
        const offcanvas = sidebar && bootstrap.Offcanvas ? bootstrap.Offcanvas.getInstance(sidebar) : null;
        offcanvas?.hide();
    });
});

document.querySelectorAll('[data-inventory-form]').forEach((form) => {
    const container = form.querySelector('[data-inventory-items]');
    const addButton = form.querySelector('[data-add-inventory-row]');
    if (!container || !addButton) {
        return;
    }

    addButton.addEventListener('click', () => {
        const source = container.querySelector('.inventory-item-row');
        if (!source) {
            return;
        }

        const row = source.cloneNode(true);
        row.querySelectorAll('input, select').forEach((field) => {
            field.value = '';
        });
        container.appendChild(row);
    });

    container.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-inventory-row]');
        if (!removeButton) {
            return;
        }

        const rows = container.querySelectorAll('.inventory-item-row');
        if (rows.length === 1) {
            rows[0].querySelectorAll('input, select').forEach((field) => {
                field.value = '';
            });
            return;
        }

        removeButton.closest('.inventory-item-row')?.remove();
    });

    form.addEventListener('submit', () => {
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.classList.add('is-loading');
            button.disabled = true;
        });
    });
});

document.querySelectorAll('[data-loading-form]:not([data-inventory-form])').forEach((form) => {
    form.addEventListener('submit', (event) => {
        form.setAttribute('aria-busy', 'true');

        if (event.submitter instanceof HTMLButtonElement) {
            event.submitter.classList.add('is-loading');
        }
    });
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const willShow = input.type === 'password';
        input.type = willShow ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(willShow));
        button.setAttribute('aria-label', willShow ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
});

document.querySelectorAll('[data-recipient-select]').forEach((select) => {
    select.addEventListener('change', () => {
        const option = select.selectedOptions[0];
        if (!option?.value) {
            return;
        }
        const values = {
            destination_name: option.dataset.name,
            destination_identifier: option.dataset.document,
            destination_address: option.dataset.address,
            route_description: option.dataset.route,
        };
        Object.entries(values).forEach(([id, value]) => {
            const input = document.getElementById(id);
            if (input instanceof HTMLInputElement) {
                input.value = value ?? '';
            }
        });
    });
});
