window.setupProductSuggestions = function (root, list, options, endpoint, mapProduct, labelOnly = false, sortField = null) {
    let timer, controller, version = 0;
    root.addEventListener('input', event => {
        const input = event.target;
        if (!input.matches('.product-search')) return;
        const query = input.value.trim();
        clearTimeout(timer);
        controller?.abort();
        const current = ++version;
        if (options.some(item => (labelOnly ? item.label : `${item.label} | ${item.barcode || ''} | ${item.description || ''}`) === query)) return;
        list.replaceChildren();
        if (query.length < 1) return;
        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const currentEndpoint = typeof endpoint === 'function' ? endpoint() : endpoint;
                const url = new URL(currentEndpoint, location.origin);
                url.searchParams.set('q', query);
                url.searchParams.set('active_only', '1');
                if (sortField) url.searchParams.set('sort', sortField);
                const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Search unavailable');
                const products = await response.json();
                if (current !== version) return;
                list.replaceChildren();
                const keywords = query.toLowerCase().split(/\s+/).filter(Boolean);
                products.map(mapProduct).filter(product => {
                    const searchableValues = [product.item_id, product.barcode, product.name, product.description, product.label]
                        .map(value => String(value || '').toLowerCase());
                    return keywords.every(keyword => searchableValues.some(value => value.includes(keyword)));
                }).forEach(product => {
                    const existing = options.findIndex(item => item.id === product.id);
                    if (existing >= 0) options[existing] = product;
                    else options.push(product);
                    const option = document.createElement('option');
                    option.value = labelOnly ? product.label : `${product.label} | ${product.barcode || ''} | ${product.description || ''}`;
                    list.append(option);
                });
            } catch (error) {
                if (error.name !== 'AbortError' && current === version) {
                    window.AppAlert?.show('Product search is unavailable. Please try again.', 'error');
                }
            }
        }, 250);
    });
};

window.transactionProductLabel = product => `${product.item_id || product.id} — ${product.name || product.description || 'Unnamed product'}${product.barcode ? ' · ' + product.barcode : ''} (stock: ${product.stock})`;
window.setupTransactionSuggestions = (container, list, options, endpoint) => {
    window.transactionProducts = window.transactionProducts || new Map();
    window.transactionProductOptions = options;
    setupProductSuggestions(container, list, options, endpoint, product => {
        window.transactionProducts.set(String(product.id), product);
        return { ...product, label: transactionProductLabel(product) };
    }, true, 'item_id');
};
