@extends('layouts.app')

@section('content')
<style>
    .national-pullout-page { max-width: 1500px; }
    .national-pullout-card { border: 1px solid var(--ac-border); border-radius: 16px; background: #fff; box-shadow: var(--ac-shadow-sm); }
    .national-pullout-items { min-width: 720px; }
    .national-pullout-items th { color: #526178; background: #f8fafc; font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; white-space: nowrap; }
    .national-pullout-items td { vertical-align: middle; }
    .pullout-product-results { position: absolute; z-index: 1080; top: calc(100% + 4px); left: 0; right: 0; max-height: 230px; overflow-y: auto; border: 1px solid #dbe3ee; border-radius: 10px; background: #fff; box-shadow: 0 12px 28px rgba(15,23,42,.13); }
    .pullout-product-result { display: block; width: 100%; padding: .65rem .75rem; border: 0; border-bottom: 1px solid #edf1f6; color: #172033; background: #fff; text-align: left; }
    .pullout-product-result:hover { background: #eff6ff; }
    .pullout-product-result small { display: block; color: #64748b; }
</style>

<div class="workspace-page national-pullout-page">
    <x-page-header class="mb-3" eyebrow="National inventory" title="National Bookstore Pullout" description="Prepare a P.O. pull-out worksheet and deduct completed quantities from National physical stock." icon="fa-book" />

    @if($errors->any())
        <div class="alert alert-danger"><strong>The pull-out was not saved.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('national-pullouts.store') }}" class="national-pullout-card p-4" id="nationalPulloutForm">
        @csrf
        <input type="hidden" name="hub_id" value="{{ $hub->id }}">

        <div class="d-flex flex-wrap gap-2 mb-4">
            <button type="button" class="btn btn-outline-primary" id="importPulloutCsv"><i class="fa-solid fa-file-import me-1"></i>Import Items (CSV)</button>
            <a class="btn btn-outline-secondary" href="{{ route('national-pullouts.worksheet', ['hub_id' => $hub->id]) }}"><i class="fa-solid fa-download me-1"></i>Download CSV Worksheet</a>
            <button type="button" class="btn btn-outline-success" id="exportPulloutSelected"><i class="fa-solid fa-file-export me-1"></i>Export Selected Items</button>
            <input type="file" class="d-none" id="pulloutCsvFile" accept=".csv,text/csv">
        </div>
        <div id="pulloutCsvMessage" class="small mb-3" role="alert" hidden></div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="pulloutPoNumber">P.O. Number</label>
                <input id="pulloutPoNumber" name="po_number" value="{{ old('po_number') }}" class="form-control" maxlength="100" placeholder="Enter P.O. number" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="pulloutDate">Date</label>
                <input id="pulloutDate" type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Head Office</label>
                <input class="form-control" value="{{ $hub->name }}" readonly>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold" for="pulloutRemarks">Remarks</label>
                <textarea id="pulloutRemarks" name="remarks" class="form-control" rows="2" maxlength="2000" placeholder="Optional remarks">{{ old('remarks') }}</textarea>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div><h5 class="fw-bold mb-1">ITEMS TO PULL-OUT</h5><small class="text-muted">Actual Pull-out is the quantity deducted from National stock.</small></div>
            <button type="button" class="btn btn-outline-primary" id="addNationalPulloutItem"><i class="fa-solid fa-plus me-1"></i>Add Item</button>
        </div>

        <div class="table-responsive border rounded-3">
            <table class="table mb-0 national-pullout-items">
                <thead><tr><th class="text-center" style="width:44px">Select</th><th style="min-width:320px">Product Item Name</th><th style="width:140px">Qty</th><th style="width:160px">Actual Pull-out</th><th style="width:70px">Action</th></tr></thead>
                <tbody id="nationalPulloutItems"></tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('inventory-transactions.index', ['hub_id' => $hub->id]) }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Save National Pullout</button>
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
    let rowIndex = 0;

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const showMessage = (text, error = false) => {
        message.hidden = false;
        message.className = `alert ${error ? 'alert-danger' : 'alert-success'} py-2 mb-3`;
        message.textContent = text;
    };
    const selectProduct = (row, product) => {
        row.querySelector('.pullout-product-search').value = product.name;
        row.querySelector('.pullout-product-id').value = product.id;
        row.querySelector('.pullout-results').hidden = true;
    };
    const renderResults = (row, term) => {
        const results = row.querySelector('.pullout-results');
        const matches = products.filter(product => {
            const haystack = `${product.name} ${product.item_id}`.toLowerCase();
            return haystack.includes(term.toLowerCase());
        }).slice(0, 20);
        results.innerHTML = matches.map(product => `<button type="button" class="pullout-product-result" data-product-id="${product.id}"><strong>${escapeHtml(product.name)}</strong><small>Item ID: ${escapeHtml(product.item_id)}</small></button>`).join('');
        results.hidden = matches.length === 0;
    };
    const addRow = (values = {}) => {
        const index = rowIndex++;
        const row = document.createElement('tr');
        row.className = 'national-pullout-item';
        row.innerHTML = `
            <td class="text-center"><input type="checkbox" class="form-check-input pullout-export-check" aria-label="Select row for CSV export"></td>
            <td><div class="position-relative"><input type="search" class="form-control pullout-product-search" placeholder="Search product name or Item ID" autocomplete="off" required><input type="hidden" class="pullout-product-id" name="items[${index}][product_id]"><div class="pullout-product-results" hidden></div></div></td>
            <td><input type="number" class="form-control pullout-qty" name="items[${index}][quantity]" min="1" step="1" required></td>
            <td><input type="number" class="form-control pullout-actual" name="items[${index}][actual_pullout]" min="1" step="1" required></td>
            <td><button type="button" class="btn btn-outline-danger remove-pullout-row" aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></td>`;
        itemsBody.appendChild(row);
        const search = row.querySelector('.pullout-product-search');
        search.addEventListener('input', () => {
            row.querySelector('.pullout-product-id').value = '';
            renderResults(row, search.value.trim());
        });
        row.querySelector('.pullout-results').addEventListener('click', event => {
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
        if (!event.target.closest('.position-relative')) document.querySelectorAll('.pullout-product-results').forEach(result => result.hidden = true);
    });
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
    const csvCell = value => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const downloadCsv = rows => {
        const csv = '\uFEFF' + rows.map(row => row.map(csvCell).join(',')).join('\r\n');
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'}));
        link.download = `NATIONAL_BOOKSTORE_PULLOUT_${(document.getElementById('pulloutPoNumber').value || 'SELECTED').replace(/[^A-Za-z0-9_-]+/g, '_')}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    };
    const exportRows = selectedOnly => {
        const rows = [...itemsBody.querySelectorAll('.national-pullout-item')].filter(row => !selectedOnly || row.querySelector('.pullout-export-check').checked);
        if (!rows.length) return showMessage('Select at least one item to export.', true);
        downloadCsv([
            ['P.O. #:', document.getElementById('pulloutPoNumber').value],
            ['Date:', document.getElementById('pulloutDate').value],
            ['Remarks:', document.getElementById('pulloutRemarks').value],
            [],
            ['Product Item Name','Qty','Actual Pull-out'],
            ...rows.map(row => [
                row.querySelector('.pullout-product-search').value,
                row.querySelector('.pullout-qty').value,
                row.querySelector('.pullout-actual').value,
            ]),
        ]);
    };
    document.getElementById('exportPulloutSelected').addEventListener('click', () => exportRows(true));

    const fileInput = document.getElementById('pulloutCsvFile');
    document.getElementById('importPulloutCsv').addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', async () => {
        if (!fileInput.files[0]) return;
        const rows = parseCsv(await fileInput.files[0].text());
        const headerIndex = rows.findIndex(row => (row[0] || '').trim().toLowerCase() === 'product item name');
        if (headerIndex < 0) return showMessage('The CSV must contain the Product Item Name header.', true);
        rows.slice(0, headerIndex).forEach(row => {
            const label = (row[0] || '').trim().toLowerCase();
            if (label === 'p.o. #:') document.getElementById('pulloutPoNumber').value = row[1] || '';
            if (label === 'date:') document.getElementById('pulloutDate').value = row[1] || '';
            if (label === 'remarks:') document.getElementById('pulloutRemarks').value = row[1] || '';
        });
        const headers = rows[headerIndex].map(value => value.trim().toLowerCase());
        const column = name => headers.indexOf(name.toLowerCase());
        const requiredHeaders = ['Product Item Name', 'Qty', 'Actual Pull-out'];
        if (requiredHeaders.some(name => column(name) < 0)) {
            return showMessage('The CSV must contain Product Item Name, Qty, and Actual Pull-out.', true);
        }
        const imported = []; const errors = [];
        rows.slice(headerIndex + 1).forEach((values, offset) => {
            const name = (values[column('Product Item Name')] || '').trim();
            const qty = (values[column('Qty')] || '').trim();
            const actual = (values[column('Actual Pull-out')] || '').trim();
            if (!name || (!qty && !actual)) return;
            const matches = byName.get(name.toLowerCase()) || [];
            if (matches.length !== 1) { errors.push(`Row ${headerIndex + offset + 2}: ${name} was not found uniquely.`); return; }
            imported.push({product: matches[0], quantity: qty, actual});
        });
        if (errors.length) return showMessage(errors.join('\n'), true);
        if (!imported.length) return showMessage('No completed pull-out rows were found in the CSV.', true);
        itemsBody.innerHTML = ''; imported.forEach(values => addRow(values));
        showMessage(`${imported.length} item row(s) imported. Review the quantities before saving.`);
        fileInput.value = '';
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
