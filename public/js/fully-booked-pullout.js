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
                        option.textContent = `${window.transactionProductLabel(product)}${product.barcode ? ` · Barcode: ${product.barcode}` : ''}`;
                        option.addEventListener('mousedown', event => event.preventDefault());
                        option.addEventListener('click', () => {
                            input.value = window.transactionProductLabel(product);
                            hidden.value = product.id;
                            input.setCustomValidity('');
                            closeResults();
                        });
                        results.append(option);
                    });
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
    };

    rows.querySelectorAll('.fully-booked-pullout-row').forEach(bindSearch);

    form.querySelector('.add-fully-booked-row').addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center fully-booked-pullout-row mb-2';
        row.innerHTML = `<div class="col-md-8"><div class="position-relative fully-booked-product-search"><input type="search" class="form-control product-search" placeholder="Type product name, item code, or barcode" autocomplete="off" aria-label="Search products" aria-autocomplete="list" aria-expanded="false" required><div class="product-search-results list-group position-absolute top-100 start-0 end-0 shadow-sm" role="listbox" hidden></div></div><input type="hidden" name="items[${nextIndex}][product_id]" class="product-id"></div><div class="col-md-3"><input type="number" name="items[${nextIndex}][quantity]" min="1" class="form-control" placeholder="Quantity" aria-label="Quantity" required></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-fully-booked-row" aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></div>`;
        rows.appendChild(row);
        bindSearch(row);
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
