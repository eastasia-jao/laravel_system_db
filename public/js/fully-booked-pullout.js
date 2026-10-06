window.exportFullyBookedCsv = (items, filename) => {
    if (!items.length) {
        window.AppAlert?.show('Select at least one product before exporting.', 'warning');
        return;
    }

    const escapeCsv = (value, preserveText = false) => {
        let text = String(value ?? '');
        if (preserveText) {
            text = `="${text.replace(/[\u0000-\u001f\u007f]/g, '').replaceAll('"', '""')}"`;
        } else if (/^[\s]*[=+\-@]/.test(text)) {
            text = `'${text}`;
        }
        return `"${text.replaceAll('"', '""')}"`;
    };
    const headers = ['Item Id', 'Description', 'Barcode', 'Physical Stock Remaining', 'Actual Pull Out', 'Actual Physical Pullout'];
    const data = [
        headers.map(value => escapeCsv(value)).join(','),
        ...items.map(row => row.map((value, index) => escapeCsv(value, index === 2)).join(',')),
    ].join('\r\n');
    const blob = new Blob(['\uFEFF', data], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.append(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
};

document.querySelectorAll('.fully-booked-pullout-form').forEach(form => {
    const rows = form.querySelector('.fully-booked-pullout-rows');
    const endpoint = form.dataset.endpoint;
    let nextIndex = 1;

    const updateRemoveButtons = () => {
        const allRows = rows.querySelectorAll('.fully-booked-pullout-row');
        allRows.forEach(row => {
            row.querySelector('.remove-fully-booked-row').disabled = allRows.length <= 1;
        });
    };

    const bindSearch = row => {
        const input = row.querySelector('.product-search');
        const hidden = row.querySelector('.product-id');
        const results = row.querySelector('.product-search-results');
        let timer;
        let controller;
        let requestVersion = 0;

        const positionResults = () => {
            if (results.hidden) return;
            const bounds = input.getBoundingClientRect();
            const below = window.innerHeight - bounds.bottom;
            const above = bounds.top;
            const openAbove = below < 160 && above > below;
            const availableHeight = Math.max(80, Math.min(260, openAbove ? above : below));

            results.style.left = `${bounds.left}px`;
            results.style.width = `${bounds.width}px`;
            results.style.maxHeight = `${availableHeight}px`;
            results.style.top = openAbove
                ? `${Math.max(0, bounds.top - availableHeight)}px`
                : `${bounds.bottom}px`;
        };

        const closeResults = () => {
            results.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        };

        input.addEventListener('input', () => {
            hidden.value = '';
            input.setCustomValidity('Choose a product from the search results.');
            clearTimeout(timer);
            controller?.abort();
            const version = ++requestVersion;
            const query = input.value.trim();
            results.replaceChildren();
            if (!query) {
                closeResults();
                return;
            }

            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            positionResults();
            const loading = document.createElement('div');
            loading.className = 'list-group-item small text-muted';
            loading.textContent = 'Searching products…';
            results.append(loading);
            timer = window.setTimeout(async () => {
                controller = new AbortController();
                try {
                    const url = new URL(endpoint, window.location.origin);
                    url.searchParams.set('q', query);
                    url.searchParams.set('active_only', '1');
                    url.searchParams.set('sort', 'item_id');
                    const response = await fetch(url, {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) throw new Error(`Product search failed (${response.status}).`);
                    const products = await response.json();
                    if (version !== requestVersion) return;

                    results.replaceChildren();
                    if (!products.length) {
                        const empty = document.createElement('div');
                        empty.className = 'list-group-item small text-muted';
                        empty.textContent = 'No matching products found.';
                        results.append(empty);
                        return;
                    }

                    products.forEach(product => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'list-group-item list-group-item-action text-start';
                        option.setAttribute('role', 'option');
                        option.textContent = window.transactionProductLabel(product);
                        option.addEventListener('mousedown', event => event.preventDefault());
                        option.addEventListener('click', () => {
                            input.value = window.transactionProductLabel(product);
                            hidden.value = product.id;
                            hidden.dataset.itemId = product.item_id || '';
                            hidden.dataset.description = product.description || product.name || '';
                            hidden.dataset.barcode = product.barcode || '';
                            hidden.dataset.stock = product.stock ?? '';
                            input.setCustomValidity('');
                            closeResults();
                        });
                        results.append(option);
                    });
                    positionResults();
                } catch (error) {
                    if (error.name === 'AbortError' || version !== requestVersion) return;
                    results.replaceChildren();
                    const failure = document.createElement('div');
                    failure.className = 'list-group-item small text-danger';
                    failure.textContent = 'Product search failed. Please try again.';
                    results.append(failure);
                    console.error('Fully Booked product search failed.', error);
                }
            }, 200);
        });

        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') closeResults();
        });
        input.addEventListener('blur', () => window.setTimeout(closeResults, 150));
        window.addEventListener('resize', positionResults);
        window.addEventListener('scroll', positionResults, true);
        rows.addEventListener('scroll', positionResults);
    };

    rows.querySelectorAll('.fully-booked-pullout-row').forEach(bindSearch);

    form.querySelector('.add-fully-booked-row').addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center fully-booked-pullout-row mb-2';
        row.innerHTML = `<div class="col-md-8"><div class="position-relative fully-booked-product-search"><input type="search" class="form-control product-search" placeholder="Type product name, item code, or barcode" autocomplete="off" aria-label="Search products" aria-autocomplete="list" aria-expanded="false" required><div class="product-search-results list-group" role="listbox" hidden></div></div><input type="hidden" name="items[${nextIndex}][product_id]" class="product-id"></div><div class="col-md-3"><input type="number" name="items[${nextIndex}][quantity]" min="1" class="form-control" placeholder="Quantity" aria-label="Quantity" required></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-fully-booked-row" aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></div>`;
        rows.appendChild(row);
        bindSearch(row);
        nextIndex++;
        updateRemoveButtons();
    });

    form.querySelector('.export-fully-booked-csv').addEventListener('click', () => {
        const selectedItems = Array.from(rows.querySelectorAll('.product-id'))
            .filter(input => input.value)
            .map(input => [
                input.dataset.itemId || '',
                input.dataset.description || '',
                input.dataset.barcode || '',
                input.dataset.stock || '',
                '',
                '',
            ]);
        window.exportFullyBookedCsv(
            selectedItems,
            `${form.dataset.orderNumber || 'fully-booked-order'}-pullout.csv`
        );
    });

    rows.addEventListener('click', event => {
        const button = event.target.closest('.remove-fully-booked-row');
        if (button && !button.disabled) {
            button.closest('.fully-booked-pullout-row').remove();
            updateRemoveButtons();
        }
    });
});

document.querySelectorAll('.export-fully-booked-details-csv').forEach(button => {
    button.addEventListener('click', () => {
        const modal = button.closest('.modal');
        const items = Array.from(modal.querySelectorAll('.fully-booked-export-item'))
            .map(item => [
                item.dataset.itemId || '',
                item.dataset.description || '',
                item.dataset.barcode || '',
                item.dataset.stock || '',
                item.dataset.quantity || '',
                '',
            ]);
        window.exportFullyBookedCsv(items, `${button.dataset.orderNumber}-pullout.csv`);
    });
});
