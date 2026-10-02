document.querySelectorAll('.fully-booked-pullout-form').forEach(form => {
    const rows = form.querySelector('.fully-booked-pullout-rows');
    const list = rows.querySelector('datalist');
    const options = [];
    window.setupProductSuggestions(rows, list, options, form.dataset.endpoint, product => ({
        ...product,
        label: window.transactionProductLabel(product),
    }), true, 'item_id');
    let nextIndex = 1;

    const updateRemoveButtons = () => {
        const allRows = rows.querySelectorAll('.fully-booked-pullout-row');
        allRows.forEach(row => {
            row.querySelector('.remove-fully-booked-row').disabled = allRows.length <= 1;
        });
    };

    rows.addEventListener('input', event => {
        const input = event.target.closest('.product-search');
        if (!input) return;
        const selectedProduct = options.find(product => product.label === input.value.trim());
        const hidden = input.closest('.fully-booked-pullout-row').querySelector('.product-id');
        hidden.value = selectedProduct?.id || '';
        input.setCustomValidity(selectedProduct ? '' : 'Select a product from the search suggestions.');
    });

    form.querySelector('.add-fully-booked-row').addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center fully-booked-pullout-row mb-2';
        row.innerHTML = `<div class="col-md-8"><input type="search" class="form-control product-search" list="${list.id}" placeholder="Search item code, barcode, or description" autocomplete="off" required><input type="hidden" name="items[${nextIndex}][product_id]" class="product-id"></div><div class="col-md-3"><input type="number" name="items[${nextIndex}][quantity]" min="1" class="form-control" placeholder="Quantity" aria-label="Quantity" required></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-fully-booked-row" aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></div>`;
        rows.appendChild(row);
        nextIndex++;
        updateRemoveButtons();
    });

    rows.addEventListener('click', event => {
        const button = event.target.closest('.remove-fully-booked-row');
        if (button && !button.disabled) {
            button.closest('.fully-booked-pullout-row').remove();
            updateRemoveButtons();
        }
    });
});
