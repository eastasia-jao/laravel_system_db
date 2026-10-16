let fullyBookedSearchPanelIndex = 0;

const exportFullyBookedExcel = async (button, items = []) => {
    const isCompletedOrder = button.dataset.completed === 'true';
    if (!isCompletedOrder && !items.length) {
        window.AppAlert?.show('Select at least one product before exporting.', 'warning');
        return;
    }

    const exportUrl = new URL(button.dataset.exportUrl, window.location.origin);
    items.forEach((item, index) => {
        exportUrl.searchParams.set(`items[${index}][product_id]`, item.productId);
        if (item.quantity) exportUrl.searchParams.set(`items[${index}][quantity]`, item.quantity);
    });

    button.disabled = true;
    try {
        const response = await fetch(exportUrl, {
            method: 'GET',
            headers: {
                Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json',
            },
        });
        if (!response.ok || !response.headers.get('Content-Type')?.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
            let message = 'The Excel worksheet could not be exported. Please try again.';
            try {
                const error = await response.json();
                message = Object.values(error.errors || {}).flat()[0] || error.message || message;
            } catch {
                // Keep the explicit default message when the server response is not JSON.
            }
            throw new Error(message);
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const filename = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/i)?.[1]
            || `${button.dataset.orderNumber || 'fully-booked-order'}-pullout.xlsx`;
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
        window.AppAlert?.show(error.message || 'The Excel worksheet could not be exported. Please try again.', 'error');
        console.error('Fully Booked Excel export failed.', error);
    } finally {
        button.disabled = false;
    }
};

document.querySelectorAll('.fully-booked-pullout-form').forEach(form => {
    const rows = form.querySelector('.fully-booked-pullout-rows');
    const exportButton = form.querySelector('.export-fully-booked-xlsx');
    exportButton?.addEventListener('click', () => {
        const items = Array.from(rows.querySelectorAll('.fully-booked-pullout-row'))
            .map(row => ({
                productId: row.querySelector('.product-id').value,
                quantity: row.querySelector('input[type="number"]').value,
            }))
            .filter(item => item.productId);
        exportFullyBookedExcel(exportButton, items);
    });
});

document.querySelectorAll('.export-fully-booked-xlsx[data-completed="true"]').forEach(button => {
    button.addEventListener('click', () => exportFullyBookedExcel(button));
});

document.querySelectorAll('.fully-booked-pullout-form').forEach(form => {
    const rows = form.querySelector('.fully-booked-pullout-rows');
    const endpoint = form.dataset.endpoint;
    const resultPanels = new WeakMap();
    let nextIndex = 1;

    const updateRemoveButtons = () => {
        const allRows = rows.querySelectorAll('.fully-booked-pullout-row');
        allRows.forEach(row => {
            row.querySelector('.remove-fully-booked-row').disabled = allRows.length <= 1;
        });
        rows.classList.toggle('is-scrollable', allRows.length >= 5);
    };

    const bindSearch = row => {
        const input = row.querySelector('.product-search');
        const hidden = row.querySelector('.product-id');
        const results = row.querySelector('.product-search-results');
        resultPanels.set(row, results);
        results.id = `fully-booked-search-results-${++fullyBookedSearchPanelIndex}`;
        input.setAttribute('aria-controls', results.id);
        document.body.append(results);
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
    updateRemoveButtons();

    form.querySelector('.add-fully-booked-row').addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center fully-booked-pullout-row mb-2';
        row.innerHTML = `<div class="col-md-8"><div class="position-relative fully-booked-product-search"><input type="search" class="form-control product-search" placeholder="Type product name, item code, or barcode" autocomplete="off" aria-label="Search products" aria-autocomplete="list" aria-expanded="false" required><div class="product-search-results list-group" role="listbox" hidden></div></div><input type="hidden" name="items[${nextIndex}][product_id]" class="product-id"></div><div class="col-md-3"><input type="number" name="items[${nextIndex}][quantity]" min="1" class="form-control" placeholder="Quantity" aria-label="Quantity" required></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-fully-booked-row" aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></div>`;
        rows.appendChild(row);
        bindSearch(row);
        nextIndex++;
        updateRemoveButtons();
    });

    rows.addEventListener('click', event => {
        const button = event.target.closest('.remove-fully-booked-row');
        if (button && !button.disabled) {
            const row = button.closest('.fully-booked-pullout-row');
            resultPanels.get(row)?.remove();
            row.remove();
            updateRemoveButtons();
        }
    });
});
