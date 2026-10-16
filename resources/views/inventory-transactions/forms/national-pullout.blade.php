@extends('layouts.app')

@section('content')
<style>
    .national-pullout-page { max-width: 1500px; }
    .national-pullout-card { border: 1px solid #e2e8f0; border-radius: 1rem; background: #fff; }
    .national-pullout-page .page-hero { margin-bottom: 1rem; }
    .national-pullout-items { min-width: 520px; }
    .national-pullout-items-scroll { max-height: 430px; overflow: auto; }
    .national-pullout-items th { color: #526178; background: #f8fafc; font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; white-space: nowrap; }
    .national-pullout-items th { position: sticky; top: 0; z-index: 2; }
    .national-pullout-items td { vertical-align: middle; }
    .pullout-product-results { position: fixed; z-index: 2000; max-height: 230px; overflow-y: auto; border: 1px solid #dbe3ee; border-radius: 10px; background: #fff; box-shadow: 0 12px 28px rgba(15,23,42,.13); }
    .pullout-product-result { display: block; width: 100%; padding: .65rem .75rem; border: 0; border-bottom: 1px solid #edf1f6; color: #172033; background: #fff; text-align: left; }
    .pullout-product-result:hover { background: #eff6ff; }
    .pullout-product-result small { display: block; color: #64748b; }
    .national-pullout-import-overlay { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 1rem; background: rgba(15, 23, 42, .48); backdrop-filter: blur(4px); }
    .national-pullout-import-overlay[hidden] { display: none; }
    .national-pullout-import-card { width: min(430px, 100%); padding: 1.5rem; border: 1px solid #e2e8f0; border-radius: 18px; background: #fff; box-shadow: 0 24px 70px rgba(15, 23, 42, .24); text-align: center; }
    .national-pullout-import-spinner { width: 54px; height: 54px; margin: 0 auto 1rem; border: 4px solid #dbeafe; border-top-color: #2563eb; border-radius: 50%; animation: national-pullout-import-spin .8s linear infinite; }
    .national-pullout-import-progress-indeterminate { overflow: hidden; }
    .national-pullout-import-progress-indeterminate .progress-bar { width: 38% !important; background-image: none; animation: national-pullout-import-slide 1.1s ease-in-out infinite; }
    @keyframes national-pullout-import-spin { to { transform: rotate(360deg); } }
    @keyframes national-pullout-import-slide { from { transform: translateX(-110%); } to { transform: translateX(270%); } }
    @media (prefers-reduced-motion: reduce) {
        .national-pullout-import-spinner, .national-pullout-import-progress-indeterminate .progress-bar { animation-duration: 2.5s; }
    }
</style>

<div class="workspace-page national-pullout-page">
    <header class="page-hero mb-3">
        <div class="page-hero-main">
            <span class="page-hero-icon" aria-hidden="true"><i class="fa-solid fa-book-open"></i></span>
            <div class="page-hero-copy">
                <div class="page-hero-eyebrow">Event / National Bookstore</div>
                <h2 class="page-hero-title">National Bookstore Pullout</h2>
                <p class="page-hero-description">Record a National Bookstore pull-out using the same event workflow, then review it in Transaction Logs.</p>
            </div>
        </div>
    </header>

    @if($errors->any())
        <div class="alert alert-danger"><strong>The pull-out was not saved.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('national-pullouts.store') }}" class="national-pullout-card p-4" id="nationalPulloutForm">
        @csrf
        <input type="hidden" name="hub_id" value="{{ $hub->id }}">

        <div class="d-flex flex-wrap gap-2 mb-4">
            <label class="btn btn-outline-primary" id="importPulloutWorksheet" for="pulloutWorksheetFile"><i class="fa-solid fa-file-import me-1"></i>Import Items (CSV / Excel)</label>
            <button type="button" class="btn btn-outline-secondary" id="downloadPulloutWorksheet"><i class="fa-solid fa-file-excel me-1"></i>Download Excel Worksheet</button>
            <label class="form-check form-check-inline mb-0 ms-1">
                <input class="form-check-input" type="checkbox" id="includePulloutWorksheetProducts" checked>
                <span class="form-check-label small">Include all product items</span>
            </label>
            <button type="button" class="btn btn-outline-success" id="exportPulloutItems"><i class="fa-solid fa-file-excel me-1"></i>Export Items (Excel)</button>
            <input type="file" class="visually-hidden" id="pulloutWorksheetFile" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" tabindex="-1" aria-label="Choose a pullout items CSV or Excel file">
        </div>
        <div id="pulloutCsvMessage" class="small mb-3" role="alert" hidden></div>
        <div id="pulloutImportLoading" class="national-pullout-import-overlay" role="status" aria-live="polite" hidden>
            <div class="national-pullout-import-card">
                <div class="national-pullout-import-spinner" aria-hidden="true"></div>
                <p class="text-primary small fw-bold text-uppercase mb-2">National Bookstore Pullout</p>
                <h2 class="h5 fw-bold mb-2">Importing your items</h2>
                <p id="pulloutImportStatus" class="text-muted mb-3">Preparing your worksheet...</p>
                <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
                    <span>Import progress</span>
                    <span id="pulloutImportProgressText" class="fw-semibold text-primary">0%</span>
                </div>
                <div id="pulloutImportProgress" class="progress" style="height: 9px;" role="progressbar" aria-label="National Bookstore import progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <div id="pulloutImportProgressBar" class="progress-bar" style="width: 0%; transition: width .12s linear;"></div>
                </div>
                <small class="d-block text-muted mt-3">Please keep this page open while your items are imported.</small>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Store Hub</label>
                <input class="form-control" value="{{ $hub->name }}" readonly aria-readonly="true">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="pulloutDate">Date</label>
                <input id="pulloutDate" type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="pulloutPoNumber">Reference Document No. (P.O. Number)</label>
                <input id="pulloutPoNumber" name="po_number" value="{{ old('po_number') }}" class="form-control" maxlength="100" placeholder="Enter P.O. number" required>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold" for="pulloutRemarks">Remarks</label>
                <textarea id="pulloutRemarks" name="remarks" class="form-control" rows="2" maxlength="2000" placeholder="Optional remarks">{{ old('remarks') }}</textarea>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div><h5 class="fw-bold mb-1">Items to Pull Out</h5><small class="text-muted">Qty is the customer request; Actual Pull-out is deducted from National stock.</small></div>
            <button type="button" class="btn btn-outline-primary" id="addNationalPulloutItem"><i class="fa-solid fa-plus me-1"></i>Add Item</button>
        </div>

        <div class="national-pullout-items-scroll border rounded-3">
            <table class="table mb-0 national-pullout-items">
                <thead><tr><th style="min-width:320px">Product Item Name</th><th style="width:110px">Qty</th><th style="width:150px">Actual Pull-out</th></tr></thead>
                <tbody id="nationalPulloutItems"></tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('inventory-transactions.index', ['hub_id' => $hub->id]) }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Save Event Items</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
(() => {
    const products = {{ Illuminate\Support\Js::from($productOptions) }};
    const byName = new Map();
    products.forEach(product => {
        const key = product.name.trim().toLowerCase();
        if (!byName.has(key)) byName.set(key, []);
        byName.get(key).push(product);
    });
    const itemsBody = document.getElementById('nationalPulloutItems');
    const message = document.getElementById('pulloutCsvMessage');
    let importedItemCount = 0;
    const hideProductResults = () => document.querySelectorAll('.pullout-product-results').forEach(result => result.hidden = true);
    const selectedProductIds = excludedRow => new Set(
        [...itemsBody.querySelectorAll('.national-pullout-item')]
            .filter(row => row !== excludedRow)
            .map(row => row.querySelector('.pullout-product-id').value)
            .filter(Boolean)
    );
    let rowIndex = 0;

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const showMessage = (text, error = false) => {
        message.hidden = false;
        message.className = `alert ${error ? 'alert-danger' : 'alert-success'} py-2 mb-3`;
        message.textContent = text;
    };
    const selectProduct = (row, product) => {
        if (selectedProductIds(row).has(String(product.id))) {
            showMessage(`${product.name} is already included in Items to Pull Out.`, true);
            return;
        }
        row.querySelector('.pullout-product-search').value = product.name;
        row.querySelector('.pullout-product-id').value = product.id;
        row.dataset.physicalStock = product.stock;
        row.querySelector('.pullout-product-results').hidden = true;
    };
    const renderResults = (row, term) => {
        const results = row.querySelector('.pullout-product-results');
        const search = row.querySelector('.pullout-product-search');
        const selectedIds = selectedProductIds(row);
        const matches = products.filter(product => {
            const haystack = `${product.name} ${product.item_id}`.toLowerCase();
            return !selectedIds.has(String(product.id)) && haystack.includes(term.toLowerCase());
        }).slice(0, 20);
        results.innerHTML = matches.map(product => `<button type="button" class="pullout-product-result" data-product-id="${product.id}"><strong>${escapeHtml(product.name)}</strong><small>Item ID: ${escapeHtml(product.item_id)}</small></button>`).join('');
        const bounds = search.getBoundingClientRect();
        results.style.left = `${bounds.left}px`;
        results.style.top = `${bounds.bottom + 4}px`;
        results.style.width = `${bounds.width}px`;
        results.hidden = matches.length === 0;
    };
    const addRow = (values = {}) => {
        const index = rowIndex++;
        const row = document.createElement('tr');
        row.className = 'national-pullout-item';
        row.innerHTML = `
            <td><div class="d-flex align-items-center gap-2"><div class="position-relative flex-grow-1"><input type="search" class="form-control pullout-product-search" placeholder="Search product name or Item ID" autocomplete="off" required><input type="hidden" class="pullout-product-id" name="items[${index}][product_id]"><div class="pullout-product-results" hidden></div></div><button type="button" class="btn btn-outline-danger remove-pullout-row" aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></div></td>
            <td><input type="number" class="form-control pullout-qty" name="items[${index}][quantity]" min="1" step="1" required></td>
            <td><input type="number" class="form-control pullout-actual" name="items[${index}][actual_pullout]" min="1" step="1" required></td>
`;
        itemsBody.appendChild(row);
        const search = row.querySelector('.pullout-product-search');
        search.addEventListener('input', () => {
            row.querySelector('.pullout-product-id').value = '';
            renderResults(row, search.value.trim());
        });
        row.querySelector('.pullout-product-results').addEventListener('click', event => {
            const button = event.target.closest('[data-product-id]');
            if (!button) return;
            selectProduct(row, products.find(product => product.id === Number(button.dataset.productId)));
        });
        row.querySelector('.remove-pullout-row').addEventListener('click', () => {
            row.remove();
            if (!itemsBody.children.length) addRow();
        });
        if (values.product) selectProduct(row, values.product);
        row.querySelector('.pullout-qty').value = values.quantity || '';
        row.querySelector('.pullout-actual').value = values.actual || '';
        return row;
    };
    document.addEventListener('click', event => {
        if (!event.target.closest('.position-relative')) hideProductResults();
    });
    document.addEventListener('scroll', hideProductResults, true);
    document.getElementById('addNationalPulloutItem').addEventListener('click', () => addRow());
    addRow();

    const parseCsv = text => {
        const rows = []; let row = []; let field = ''; let quoted = false;
        for (let i = 0; i < text.length; i++) {
            const char = text[i];
            if (quoted) {
                if (char === '"' && text[i + 1] === '"') { field += '"'; i++; }
                else if (char === '"') quoted = false;
                else field += char;
            } else if (char === '"') quoted = true;
            else if (char === ',') { row.push(field); field = ''; }
            else if (char === '\n') { row.push(field.replace(/\r$/, '')); rows.push(row); row = []; field = ''; }
            else field += char;
        }
        if (field !== '' || row.length) { row.push(field.replace(/\r$/, '')); rows.push(row); }
        return rows;
    };
    document.getElementById('downloadPulloutWorksheet').addEventListener('click', () => {
        const parameters = new URLSearchParams({
            hub_id: document.querySelector('[name="hub_id"]').value,
            po_number: document.getElementById('pulloutPoNumber').value.trim(),
            occurred_on: document.getElementById('pulloutDate').value,
            remarks: document.getElementById('pulloutRemarks').value.trim(),
            include_products: document.getElementById('includePulloutWorksheetProducts').checked ? '1' : '0',
        });
        window.location.assign(`{{ route('national-pullouts.excel-worksheet') }}?${parameters.toString()}`);
    });
    document.getElementById('exportPulloutItems').addEventListener('click', () => {
        const rows = [...itemsBody.querySelectorAll('.national-pullout-item')]
            .filter(row => row.querySelector('.pullout-product-id').value);
        if (!rows.length) return showMessage('Add at least one product before exporting.', true);
        const exportForm = document.createElement('form');
        exportForm.method = 'POST';
        exportForm.action = '{{ route('national-pullouts.export-excel-worksheet') }}';
        const fields = {
            _token: document.querySelector('#nationalPulloutForm [name="_token"]').value,
            hub_id: document.querySelector('[name="hub_id"]').value,
            po_number: document.getElementById('pulloutPoNumber').value.trim(),
            occurred_on: document.getElementById('pulloutDate').value,
            remarks: document.getElementById('pulloutRemarks').value.trim(),
        };
        Object.entries(fields).forEach(([name, value]) => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; exportForm.appendChild(input);
        });
        rows.forEach((row, index) => {
            [['product_id', '.pullout-product-id'], ['quantity', '.pullout-qty'], ['actual_pullout', '.pullout-actual']].forEach(([field, selector]) => {
                const input = document.createElement('input'); input.type = 'hidden'; input.name = `items[${index}][${field}]`; input.value = row.querySelector(selector).value; exportForm.appendChild(input);
            });
        });
        document.body.appendChild(exportForm);
        exportForm.submit();
        exportForm.remove();
    });

    const importRows = async (sourceRows, sourceLabel) => {
        const imported = []; const errors = []; const importedIds = new Set();
        for (let start = 0; start < sourceRows.length; start += 100) {
            sourceRows.slice(start, start + 100).forEach((values, index) => {
                const offset = start + index;
                const name = String(values.name || '').trim();
                const qty = String(values.quantity ?? '').trim();
                const actual = String(values.actual_pullout ?? '').trim();
                if (!name || (!qty && !actual)) return;
                const matches = byName.get(name.toLowerCase()) || [];
                if (matches.length !== 1) { errors.push(`Row ${values.row || offset + 1}: ${name} was not found uniquely.`); return; }
                if (importedIds.has(String(matches[0].id))) { errors.push(`Row ${values.row || offset + 1}: ${name} is duplicated.`); return; }
                importedIds.add(String(matches[0].id));
                imported.push({product: matches[0], quantity: qty, actual});
            });
            updateImportProgress(35 + (Math.min(start + 100, sourceRows.length) / Math.max(sourceRows.length, 1)) * 40);
            await new Promise(resolve => requestAnimationFrame(resolve));
        }
        if (errors.length) { showMessage(errors.join('\n'), true); return false; }
        if (!imported.length) { showMessage(`No completed pull-out rows were found in the ${sourceLabel}.`, true); return false; }
        setImportIndeterminate('Adding imported items to the pullout form...');
        itemsBody.innerHTML = '';
        for (let start = 0; start < imported.length; start += 50) {
            imported.slice(start, start + 50).forEach(values => addRow(values));
            updateImportProgress(75 + (Math.min(start + 50, imported.length) / imported.length) * 24);
            await new Promise(resolve => requestAnimationFrame(resolve));
        }
        message.hidden = true;
        message.textContent = '';
        importedItemCount = imported.length;
        return true;
    };

    const fileInput = document.getElementById('pulloutWorksheetFile');
    const importButton = document.getElementById('importPulloutWorksheet');
    const importLoading = document.getElementById('pulloutImportLoading');
    const importStatus = document.getElementById('pulloutImportStatus');
    const importProgress = document.getElementById('pulloutImportProgress');
    const importProgressBar = document.getElementById('pulloutImportProgressBar');
    const importProgressText = document.getElementById('pulloutImportProgressText');
    const updateImportProgress = value => {
        const progress = Math.min(100, Math.max(0, Math.round(value)));
        importProgress.classList.remove('national-pullout-import-progress-indeterminate');
        importProgress.setAttribute('aria-valuenow', String(progress));
        importProgress.removeAttribute('aria-valuetext');
        importProgressBar.style.width = `${progress}%`;
        importProgressText.textContent = `${progress}%`;
    };
    const setImportIndeterminate = text => {
        importStatus.textContent = text;
        importProgress.classList.add('national-pullout-import-progress-indeterminate');
        importProgress.removeAttribute('aria-valuenow');
        importProgress.setAttribute('aria-valuetext', 'Working');
        importProgressText.textContent = 'Working…';
    };
    const readCsvFile = file => new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.addEventListener('progress', event => {
            if (event.lengthComputable) updateImportProgress(35 * event.loaded / event.total);
        });
        reader.addEventListener('load', () => resolve(String(reader.result ?? '')));
        reader.addEventListener('error', () => reject(new Error('Unable to read the selected CSV file. Please try again.')));
        reader.addEventListener('abort', () => reject(new Error('CSV import was cancelled.')));
        reader.readAsText(file);
    });
    const uploadExcelFile = formData => new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open('POST', '{{ route('national-pullouts.import-excel-worksheet') }}');
        request.setRequestHeader('Accept', 'application/json');
        request.upload.addEventListener('progress', event => {
            if (event.lengthComputable) updateImportProgress(35 * event.loaded / event.total);
        });
        request.upload.addEventListener('load', () => setImportIndeterminate('Checking the Excel worksheet and matching products...'));
        request.addEventListener('load', () => {
            let data = {};
            try { data = JSON.parse(request.responseText); }
            catch { reject(new Error('The server returned an invalid response while importing the Excel worksheet.')); return; }
            if (request.status < 200 || request.status >= 300) {
                reject(new Error(data.errors?.worksheet?.[0] || data.message || 'The Excel worksheet could not be imported.'));
                return;
            }
            resolve(data);
        });
        request.addEventListener('error', () => reject(new Error('Network error while importing the Excel worksheet. Please retry.')));
        request.addEventListener('abort', () => reject(new Error('Excel worksheet import was cancelled.')));
        request.send(formData);
    });
    fileInput.addEventListener('change', async () => {
        if (!fileInput.files[0]) return;
        importLoading.hidden = false;
        importButton.setAttribute('aria-disabled', 'true');
        fileInput.disabled = true;
        updateImportProgress(0);
        let imported = false;
        try {
            const file = fileInput.files[0];
            if (file.name.toLowerCase().endsWith('.xlsx')) {
                importStatus.textContent = 'Uploading the Excel worksheet...';
                const formData = new FormData();
                formData.append('_token', document.querySelector('#nationalPulloutForm [name="_token"]').value);
                formData.append('hub_id', document.querySelector('[name="hub_id"]').value);
                formData.append('worksheet', file);
                const data = await uploadExcelFile(formData);
                if (!await importRows(data.items || [], 'Excel worksheet')) return;
                if (data.po_number) document.getElementById('pulloutPoNumber').value = data.po_number;
                if (data.occurred_on) document.getElementById('pulloutDate').value = data.occurred_on;
                if (data.remarks) document.getElementById('pulloutRemarks').value = data.remarks;
            } else {
                importStatus.textContent = 'Reading the CSV worksheet...';
                const text = (await readCsvFile(file)).replace(/^\uFEFF/, '');
                setImportIndeterminate('Reading and validating the CSV rows...');
                await new Promise(resolve => requestAnimationFrame(resolve));
                const rows = parseCsv(text);
                const headerIndex = rows.findIndex(row => (row[0] || '').trim().toLowerCase() === 'product item name');
                if (headerIndex < 0) throw new Error('The CSV must contain the Product Item Name header.');
                const headers = rows[headerIndex].map(value => value.trim().toLowerCase());
                const column = name => headers.indexOf(name.toLowerCase());
                // Purpose and Physical Stocks are reference columns; import only request and actual pull-out quantities.
                const requiredHeaders = ['Product Item Name', 'Qty', 'Actual Pull-Out'];
                if (requiredHeaders.some(name => column(name) < 0)) {
                    throw new Error('The CSV must contain Product Item Name, Qty, and Actual Pull-Out.');
                }
                const items = rows.slice(headerIndex + 1).map((values, offset) => ({
                    name: values[column('Product Item Name')],
                    quantity: values[column('Qty')],
                    actual_pullout: values[column('Actual Pull-Out')],
                    row: headerIndex + offset + 2,
                }));
                if (!await importRows(items, 'CSV')) return;
            }
            imported = true;
        } catch (error) {
            showMessage(error.message || 'The selected worksheet could not be read. Please choose a valid CSV or Excel file and try again.', true);
            console.error('National Bookstore Pullout worksheet import failed.', error);
        } finally {
            fileInput.value = '';
            fileInput.disabled = false;
            importButton.removeAttribute('aria-disabled');
            if (imported) {
                importStatus.textContent = 'Import complete';
                updateImportProgress(100);
                await new Promise(resolve => setTimeout(resolve, 350));
            }
            importLoading.hidden = true;
            if (imported) {
                await window.AppAlert.show(
                    `${importedItemCount} item row(s) imported. Review the quantities before saving.`,
                    'success',
                    { title: 'Pullout items imported', buttonLabel: 'Continue' }
                );
            }
        }
    });

    document.getElementById('nationalPulloutForm').addEventListener('submit', event => {
        const invalid = [...itemsBody.querySelectorAll('.national-pullout-item')].find(row => !row.querySelector('.pullout-product-id').value);
        if (invalid) {
            event.preventDefault();
            showMessage('Select every product from the search results before saving.', true);
            invalid.querySelector('.pullout-product-search').focus();
        }
    });
})();
</script>
@endpush
@endsection
