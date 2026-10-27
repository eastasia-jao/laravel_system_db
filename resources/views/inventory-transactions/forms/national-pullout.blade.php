@extends('layouts.app')

@section('content')
<style>
    .national-pullout-page { max-width: 1500px; }
    .national-pullout-card { border: 1px solid #e2e8f0; border-radius: 1rem; background: #fff; }
    .national-pullout-worksheet-option { display: flex; width: min(460px, 100%); align-items: center; justify-content: space-between; gap: 1rem; margin-top: -.75rem; margin-bottom: 1.25rem; padding: .65rem .85rem; border: 1px solid #e2e8f0; border-radius: .65rem; background: #f8fafc; }
    .national-pullout-page .page-hero { margin-bottom: 1rem; }
    .national-pullout-items { min-width: 520px; }
    .national-pullout-items-scroll { max-height: 430px; overflow: auto; }
    .national-pullout-items th { color: #526178; background: #f8fafc; font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; white-space: nowrap; }
    .national-pullout-items th { position: sticky; top: 0; z-index: 2; }
    .national-pullout-items td { vertical-align: middle; }
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
            <label class="btn btn-outline-primary" id="importPulloutWorksheet" for="pulloutWorksheetFile"><i class="fa-solid fa-file-import me-1"></i>Import Items (Excel)</label>
            <button type="button" class="btn btn-outline-secondary" id="downloadPulloutWorksheet"><i class="fa-solid fa-file-excel me-1"></i>Download Excel Worksheet</button>
            <button type="button" class="btn btn-outline-success" id="exportPulloutItems"><i class="fa-solid fa-file-excel me-1"></i>Export Items (Excel)</button>
            <input type="file" class="visually-hidden" id="pulloutWorksheetFile" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" tabindex="-1" aria-label="Choose a pullout items Excel file">
        </div>
        <div class="national-pullout-worksheet-option">
            <div>
                <div class="small fw-semibold">Worksheet product list</div>
                <div class="small text-muted">Include all products in the downloaded template.</div>
            </div>
            <label class="form-check form-switch mb-0 flex-shrink-0">
                <input class="form-check-input" type="checkbox" role="switch" id="includePulloutWorksheetProducts" aria-label="Include all product items in the worksheet" checked>
            </label>
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
                <textarea id="pulloutRemarks" name="remarks" class="form-control" rows="2" maxlength="150" placeholder="Optional remarks">{{ old('remarks') }}</textarea>
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
    const productLabel = product => `${product.item_id || product.id} — ${product.name || 'Unnamed product'} (stock: ${product.stock})`;
    const productsByLabel = new Map(products.map(product => [productLabel(product).toLowerCase(), product]));
    const dataList = document.createElement('datalist');
    dataList.id = 'nationalPulloutProductOptions';
    products.forEach(product => {
        const option = document.createElement('option');
        option.value = productLabel(product);
        dataList.appendChild(option);
    });
    document.body.appendChild(dataList);
    const itemsBody = document.getElementById('nationalPulloutItems');
    const message = document.getElementById('pulloutCsvMessage');
    let importedItemCount = 0;
    const selectedProductIds = excludedRow => new Set(
        [...itemsBody.querySelectorAll('.national-pullout-item')]
            .filter(row => row !== excludedRow)
            .map(row => row.querySelector('.pullout-product-id').value)
            .filter(Boolean)
    );
    let rowIndex = 0;

    const showMessage = (text, error = false) => {
        message.hidden = false;
        message.className = `alert ${error ? 'alert-danger' : 'alert-success'} py-2 mb-3`;
        message.textContent = text;
    };
    const setProduct = (row, product) => {
        const search = row.querySelector('.pullout-product-search');
        const productId = row.querySelector('.pullout-product-id');
        if (!product) {
            productId.value = '';
            search.setCustomValidity('Select a product from the search suggestions.');
            return;
        }
        search.value = productLabel(product);
        productId.value = product.id;
        search.setCustomValidity('');
        if (selectedProductIds(row).has(String(product.id))) {
            showMessage(`${product.name} is already included in Items to Pull Out.`, true);
            search.value = '';
            productId.value = '';
            search.setCustomValidity('Select a product from the search suggestions.');
            return;
        }
        row.dataset.physicalStock = product.stock;
    };
    const addRow = (values = {}) => {
        const index = rowIndex++;
        const row = document.createElement('tr');
        row.className = 'national-pullout-item';
        row.innerHTML = `
            <td><div class="d-flex align-items-center gap-2"><input type="search" class="form-control pullout-product-search" list="nationalPulloutProductOptions" placeholder="Search product name or Item ID" autocomplete="off" required><input type="hidden" class="pullout-product-id" name="items[${index}][product_id]"><button type="button" class="btn btn-outline-danger remove-pullout-row" aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></div></td>
            <td><input type="number" class="form-control pullout-qty" name="items[${index}][quantity]" min="1" step="1" required></td>
            <td><input type="number" class="form-control pullout-actual" name="items[${index}][actual_pullout]" min="1" step="1" required></td>
`;
        itemsBody.appendChild(row);
        const search = row.querySelector('.pullout-product-search');
        search.addEventListener('input', () => {
            const product = productsByLabel.get(search.value.trim().toLowerCase());
            setProduct(row, product || null);
        });
        row.querySelector('.remove-pullout-row').addEventListener('click', () => {
            row.remove();
            if (!itemsBody.children.length) addRow();
        });
        if (values.product) setProduct(row, values.product);
        row.querySelector('.pullout-qty').value = values.quantity || '';
        row.querySelector('.pullout-actual').value = values.actual || '';
        return row;
    };
    document.getElementById('addNationalPulloutItem').addEventListener('click', () => addRow());
    addRow();

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
            if (!file.name.toLowerCase().endsWith('.xlsx')) throw new Error('Choose an Excel workbook in .xlsx format.');
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
            imported = true;
        } catch (error) {
            showMessage(error.message || 'The selected worksheet could not be read. Please choose a valid Excel file and try again.', true);
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
            showMessage('Select every product from the search suggestions before saving.', true);
            invalid.querySelector('.pullout-product-search').focus();
        }
    });
})();
</script>
@endpush
@endsection
