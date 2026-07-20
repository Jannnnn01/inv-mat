import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

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
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    });
});
