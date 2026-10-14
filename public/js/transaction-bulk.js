export function parseCsv(text) {
    const rows = []; let row = [], value = '', quoted = false;
    text = text.replace(/^\uFEFF/, '');
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (c === '"') {
            if (quoted && text[i + 1] === '"') { value += '"'; i++; }
            else if (quoted || value === '') quoted = !quoted;
            else throw new Error('Invalid CSV quotation. Save the worksheet as CSV again.');
        } else if (c === ',' && !quoted) { row.push(value); value = ''; }
        else if ((c === '\n' || c === '\r') && !quoted) {
            if (c === '\r' && text[i + 1] === '\n') i++;
            row.push(value); rows.push(row); row = []; value = '';
        } else value += c;
    }
    if (quoted) throw new Error('The CSV has an unclosed quotation mark.');
    if (value || row.length) { row.push(value); rows.push(row); }
    return rows;
}
const clean = value => String(value ?? '').trim();
export function readIdentifier(value) {
    let text = clean(value);
    const literal = text.match(/^="([0-9]+)"$/);
    if (literal) text = literal[1];
    if (/^[+-]?[0-9]+(?:\.[0-9]+)?e[+-]?[0-9]+$/i.test(text)) {
        throw new Error(`Identifier ${text} uses scientific notation. Enter the original full barcode / Item ID as text and retry; digits cannot be guessed.`);
    }
    return text;
}
export function findReviewProductRow(rows, message) {
    const productName = message.match(/Not enough (?:(?:good|damaged) )?stock for (.+?)(?:\.|$)/i)?.[1]?.trim()
        || message.match(/The product (.+?) is not listed in the selected target store/i)?.[1]?.trim();
    if (!productName) return null;
    const normalizedName = productName.toLocaleLowerCase();
    return [...rows].find(row => row.querySelector('.product-search')?.value
        .toLocaleLowerCase().includes(normalizedName)) || null;
}
export function exportFilename(type, hubCode, date = new Date(), transferDirection = 'ho_to_branch') {
    const label = type === 'stock_transfer'
        ? (transferDirection === 'branch_to_ho' ? 'StockTrf_B2HO' : 'StockTrf_HO2B')
        : { branch_transfer: 'StockTrf_B2B', sponsor_workshop: 'SponsorWorkshop', restock: 'Restock', return: 'Return' }[type] || 'Products';
    const hubSlug = String(hubCode ?? '').trim().toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_|_$/g, '') || 'STORE';
    const timestamp = date.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '').replace('T', '_').replace('Z', '');
    return `${hubSlug}_Exp_${label}_${timestamp}.csv`;
}
export function importRows(text, products, type) {
    const rows = parseCsv(text), headers = (rows.shift() || []).map(v => clean(v).toLowerCase());
    const itemColumn = headers.indexOf('item id'), barcodeColumn = headers.indexOf('barcode');
    const quantityColumn = headers.findIndex(h => ['quantity', 'actual pull out qty', 'physical actual pullout', 'actual added qty', 'actual return qty'].includes(h));
    const conditionColumn = headers.findIndex(h => ['condition', 'stock condition'].includes(h));
    if ((itemColumn < 0 && barcodeColumn < 0) || quantityColumn < 0) throw new Error('CSV requires Item ID or Barcode, and a Quantity column (or the worksheet quantity heading).');
    const index = key => {
        const map = new Map();
        products.forEach(p => { const v = clean(p[key]); if (v) map.set(v, [...(map.get(v) || []), p]); });
        return map;
    };
    const ids = index('item_id'), barcodes = index('barcode'), items = [], errors = [];
    rows.forEach((row, offset) => {
        if (row.every(v => !clean(v))) return;
        const quantity = clean(row[quantityColumn]);
        if (!quantity) return;
        let id, barcode;
        try {
            id = readIdentifier(row[itemColumn]); barcode = readIdentifier(row[barcodeColumn]);
        } catch (error) { errors.push(`Row ${offset + 2}: ${error.message}`); return; }
        let matches = id ? (ids.get(id) || []) : (barcodes.get(barcode) || []);
        if (id && barcode) matches = matches.filter(p => clean(p.barcode) === barcode);
        const fail = message => errors.push(`Row ${offset + 2}: ${message}`);
        if (matches.length !== 1) return fail(`${id || barcode || '(missing identifier)'} is missing, ambiguous, or does not match the selected store's active List of Products.`);
        if (!/^[1-9]\d*$/.test(quantity) || !Number.isSafeInteger(Number(quantity))) return fail('Quantity must be a positive whole number.');
        const condition = clean(row[conditionColumn]).toLowerCase();
        if (type === 'return' && !['good', 'damaged'].includes(condition)) return fail('Condition must be good or damaged.');
        if (type === 'sponsor_workshop' && condition && !['good', 'damaged'].includes(condition)) return fail('Stock condition must be good or damaged.');
        items.push({
            product_id: matches[0].id,
            quantity: Number(quantity),
            ...(type === 'return' ? { condition } : {}),
            ...(type === 'sponsor_workshop' ? { stock_condition: condition || 'good', pullout_qty: Number(quantity) } : {})
        });
    });
    if (errors.length) throw new Error(`No items imported. Fix these errors and retry:\n${errors.join('\n')}`);
    if (!items.length) throw new Error('No quantities found. Fill in at least one quantity before importing.');
    return items;
}
export function csvText(rows, identifierColumns = []) {
    return '\uFEFF' + rows.map((row, rowIndex) => row.map((value, column) => {
        let text = String(value ?? '');
        if (rowIndex > 0 && identifierColumns.includes(column)) {
            text = readIdentifier(text);
            if (/^[0-9]+$/.test(text)) text = `="${text}"`;
            else if (/^[=+\-@\t\r\n]/.test(text)) text = "'" + text;
        } else if (/^[=+\-@\t\r\n]/.test(text)) text = "'" + text;
        return '"' + text.replaceAll('"', '""') + '"';
    }).join(',')).join('\r\n');
}
export function setupBulkItems({ products, type, hubCode, endpoint, transferDirection = 'ho_to_branch', worksheetUrl = null, isActive = () => true }) {
    const panel = document.getElementById('transactionBulk'), form = panel.closest('form');
    const prefix = { stock_transfer: 'transfer', branch_transfer: 'transfer', sponsor_workshop: 'sponsor', restock: 'restock', return: 'return' }[type];
    const container = document.getElementById(prefix + 'Items'), template = container.querySelector('.' + prefix + '-item').cloneNode(true);
    const file = document.getElementById('transactionCsv'), message = document.getElementById('transactionBulkMessage');
    const importButton = panel.querySelector('[data-bulk="import"]');
    const importLoading = document.getElementById('transactionImportLoading');
    const importLoadingText = document.getElementById('transactionImportLoadingText');
    const importProgressBar = document.getElementById('transactionImportProgressBar');
    const importProgressText = document.getElementById('transactionImportProgressText');
    const importProgress = importProgressBar.parentElement;
    const byId = window.transactionProducts || new Map();
    products.forEach(p => byId.set(String(p.id), p));
    window.transactionProducts = byId;
    const options = [...document.getElementById(prefix + 'ProductOptions').options];
    const labels = new Map(products.map((p, i) => [String(p.id), options[i].value]));
    const labelIds = new Map([...labels].map(([id, label]) => [label.toLowerCase(), id]));
    const eventImportUrl = importButton.dataset.bulkImportUrl;
    const eventExportUrl = panel.querySelector('[data-bulk="export"]').dataset.bulkExportUrl;
    const csrfToken = form.querySelector('input[name="_token"]')?.value || '';
    const eventMetadata = () => ({
        hub_id: form.querySelector('[name="store_hub_id"]')?.value || '',
        occurred_on: form.querySelector('[name="occurred_on"]')?.value || '',
        source: form.querySelector('[name="source"]')?.value || '',
        reference: form.querySelector('[name="reference"]')?.value || '',
        notes: form.querySelector('[name="notes"]')?.value || ''
    });
    const downloadBlob = (blob, filename) => {
        const url = URL.createObjectURL(blob), link = document.createElement('a');
        link.href = url; link.download = filename; link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    };
    const downloadEventWorkbook = async items => {
        const response = await fetch(eventExportUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ ...eventMetadata(), items })
        });
        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(Object.values(body.errors || {}).flat().join('\n') || body.message || 'Unable to export the Event workbook. Please retry.');
        }
        const filename = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/i)?.[1]
            || exportFilename(type, hubCode, new Date()).replace(/\.csv$/i, '.xlsx');
        downloadBlob(await response.blob(), filename);
    };
    const readEventWorkbook = async upload => {
        const data = new FormData();
        data.set('file', upload);
        data.set('hub_id', eventMetadata().hub_id);
        const response = await fetch(eventImportUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: data
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(Object.values(body.errors || {}).flat().join('\n') || body.message || 'Unable to import the Event workbook. Please retry.');
        }
        const metadata = body.metadata || {};
        for (const [key, value] of Object.entries(metadata)) {
            const field = form.querySelector(`[name="${key}"]`);
            if (field && value) field.value = value;
        }
        return body.items;
    };
    panel.querySelector('[data-bulk-worksheet-url]')?.addEventListener('click', event => {
        if (!worksheetUrl) return;
        event.preventDefault();
        const url = new URL(worksheetUrl, location.origin);
        Object.entries(eventMetadata()).forEach(([key, value]) => {
            if (value) url.searchParams.set(key, value);
        });
        window.location.href = url.toString();
    });
    let catalogLoaded = !endpoint, loading;
    const loadCatalog = () => {
        if (catalogLoaded) return Promise.resolve();
        if (loading) return loading;
        loading = (async () => {
            importLoadingText.textContent = 'Loading the store product list...';
            let after = 0;
            const known = new Set(products.map(p => String(p.id)));
            const selectable = window.transactionProductOptions || [];
            const selectableIds = new Set(selectable.map(p => String(p.id)));
            try {
                while (true) {
                    const url = new URL(endpoint, location.origin);
                    url.searchParams.set('inventory_page', '1'); url.searchParams.set('after', String(after));
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('Could not load branch products. Please retry.');
                    const batch = await response.json();
                    for (const product of batch) {
                        const id = String(product.id), label = labels.get(id) || window.transactionProductLabel(product);
                        byId.set(id, product); labels.set(id, label); labelIds.set(label.toLowerCase(), id);
                        if (!known.has(id)) { products.push(product); known.add(id); }
                        if (!selectableIds.has(id)) { selectable.push({ ...product, label }); selectableIds.add(id); }
                    }
                    if (batch.length < 500) break;
                    const next = Number(batch[batch.length - 1].id);
                    if (next <= after) throw new Error('Product loading did not advance. Please retry.');
                    after = next;
                }
                catalogLoaded = true;
            } finally { message.textContent = ''; loading = null; }
        })();
        return loading;
    };
    const show = (text, error = false) => {
        window.AppAlert.show(text, error ? 'error' : 'success', {
            title: error ? 'Check your transaction items' : 'Your items are ready',
            buttonLabel: error ? 'Review items' : 'Continue',
        }).then(() => {
            if (!error) return;
            const row = findReviewProductRow(itemRows(), text);
            if (!row) return;
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.classList.remove('transaction-item-review-highlight');
            void row.offsetWidth;
            row.classList.add('transaction-item-review-highlight');
            row.querySelector('[name$="[quantity]"]')?.focus({ preventScroll: true });
            setTimeout(() => row.classList.remove('transaction-item-review-highlight'), 2200);
        });
    };
    const itemRows = () => [...container.querySelectorAll(`.${prefix}-item`)];
    const selected = () => itemRows().map(row => ({
        product_id: row.querySelector('.product-id').value,
        quantity: row.querySelector('[name$="[quantity]"]').value,
        ...(type === 'return' ? { condition: row.querySelector('select').value } : {}),
        ...(type === 'sponsor_workshop' ? {
            stock_condition: row.querySelector('[name$="[stock_condition]"]').value,
            pullout_qty: row.querySelector('[name$="[pullout_qty]"]').value
        } : {})
    }));
    const heading = type === 'restock' ? 'Actual Added Qty' : type === 'return' ? 'Actual Return Qty' : 'Physical Actual Pullout';
    const download = items => {
        const conditionHeading = type === 'return' ? ['Condition'] : type === 'sponsor_workshop' ? ['Stock Condition'] : [];
        const rows = [['Name / Description', 'Barcode', 'Item ID', 'Physical Stocks Qty', heading, ...conditionHeading]];
        items.forEach(item => {
            const p = byId.get(String(item.product_id));
            const condition = type === 'return' ? item.condition : item.stock_condition;
            rows.push([p.description || p.name, p.barcode, p.item_id, p.stock, item.quantity, ...(conditionHeading.length ? [condition || ''] : [])]);
        });
        let csv;
        try { csv = csvText(rows, [1, 2]); }
        catch (error) { show(error.message, true); return; }
        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a'); link.href = url; link.download = exportFilename(type, hubCode, new Date(), transferDirection); link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    };
    panel.querySelector('[data-bulk="export"]').onclick = () => {
        const items = selected().filter(i => i.product_id || i.quantity);
        if (!items.length || items.some(i => !byId.has(i.product_id) || !/^[1-9]\d*$/.test(i.quantity) || (type === 'return' && !i.condition) || (type === 'sponsor_workshop' && (!i.stock_condition || (i.pullout_qty !== '' && !/^(0|[1-9]\d*)$/.test(i.pullout_qty)))))) return show('Select valid products and quantities (and applicable conditions) before exporting.', true);
        if (type === 'sponsor_workshop') {
            downloadEventWorkbook(items).catch(error => show(error.message, true));
            return;
        }
        download(items);
    };
    panel.querySelector('[data-bulk="import"]').onclick = () => file.click();
    file.onchange = async () => {
        if (!file.files.length) return;
        importButton.disabled = true;
        file.disabled = true;
        importLoading.hidden = false;
        let progress = 1;
        let imported = false;
        const updateProgress = value => {
            progress = Math.min(100, Math.max(1, value));
            importProgressBar.style.width = `${progress}%`;
            importProgressText.textContent = `${progress}%`;
            importProgress.setAttribute('aria-valuenow', String(progress));
        };
        updateProgress(progress);
        const progressTimer = setInterval(() => {
            if (progress < 95) updateProgress(progress + 1);
        }, 40);
        try {
            await new Promise(resolve => requestAnimationFrame(resolve));
            const upload = file.files[0];
            if (upload.size > 10 * 1024 * 1024) throw new Error('Choose a file smaller than 10 MB.');
            await loadCatalog();
            importLoadingText.textContent = 'Reading and validating the selected file...';
            const items = type === 'sponsor_workshop' && /\.xlsx$/i.test(upload.name)
                ? await readEventWorkbook(upload)
                : importRows(await upload.text(), products, type);
            importLoadingText.textContent = 'Preparing imported items...';
            const fragment = document.createDocumentFragment();
            items.forEach(item => {
                const row = template.cloneNode(true), search = row.querySelector('.product-search'), hidden = row.querySelector('.product-id');
                search.value = labels.get(String(item.product_id)); hidden.value = item.product_id;
                search.addEventListener('input', () => {
                    hidden.value = labelIds.get(search.value.trim().toLowerCase()) || '';
                    search.setCustomValidity(hidden.value ? '' : 'Select a product from the search suggestions.');
                });
                row.querySelector('[name$="[quantity]"]').value = item.quantity;
                if (type === 'return') row.querySelector('select').value = item.condition;
                if (type === 'sponsor_workshop') {
                    row.querySelector('[name$="[stock_condition]"]').value = item.stock_condition;
                    row.querySelector('[name$="[pullout_qty]"]').value = item.pullout_qty ?? '';
                }
                row.querySelector('button').disabled = false;
                fragment.appendChild(row);
            });
            itemRows().forEach(row => {
                if (!row.querySelector('.product-search').value && !row.querySelector('[name$="[quantity]"]').value) row.remove();
            });
            container.appendChild(fragment);
            if (type === 'stock_transfer' || type === 'branch_transfer') {
                const transferRows = container.querySelectorAll('.transfer-item');
                transferRows.forEach(row => {
                    row.querySelector('.remove-transfer-item').disabled = transferRows.length <= 1;
                });
            }
            show(`${items.length} items imported. Review the quantities, then save the transaction.`);
            imported = true;
        } catch (error) { show(error.message, true); }
        finally {
            clearInterval(progressTimer);
            if (imported) {
                importLoadingText.textContent = 'Import complete';
                updateProgress(100);
                await new Promise(resolve => setTimeout(resolve, 350));
            }
            file.value = '';
            file.disabled = false;
            importButton.disabled = false;
            importLoading.hidden = true;
        }
    };
    form.addEventListener('submit', event => {
        if (!isActive()) return;
        event.preventDefault();
        const items = selected();
        if (!items.length || items.some(i => !byId.has(i.product_id))) return show('Select at least one product from the List of Products.', true);
        const data = new FormData(form);
        for (const key of [...data.keys()]) if (key.startsWith('items[')) data.delete(key);
        data.set('items_json', JSON.stringify(items));
        const submit = form.querySelector('button:not([type])');
        if (submit) submit.disabled = true;
        fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json' } })
            .then(async response => {
                if (response.redirected && response.ok) { window.location.href = response.url; return; }
                const body = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(Object.values(body.errors || {}).flat().join('\n') || body.message || 'Unable to save. Please retry.');
                window.location.href = response.url;
            }).catch(error => show(error.message, true)).finally(() => { if (submit) submit.disabled = false; });
    });
}
