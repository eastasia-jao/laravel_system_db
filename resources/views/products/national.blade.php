@extends('layouts.app')

@section('content')
<style>
    .national-inventory-page { max-width: 1500px; }
    .national-inventory-card { border: 1px solid var(--ac-border); border-radius: 16px; background: var(--ac-surface); box-shadow: var(--ac-shadow-sm); }
    .national-inventory-table th { white-space: nowrap; }
    .national-inventory-table td { vertical-align: middle; }
    .national-scope-note { display: flex; align-items: flex-start; gap: .55rem; padding: .75rem .9rem; border: 1px solid #bfdbfe; border-radius: 11px; background: #eff6ff; color: #1e40af; font-size: .78rem; }
    .inventory-scope-tabs { display: flex; flex-wrap: wrap; gap: .5rem; }
    .national-selection-col { display: none; }
    .national-selection-mode .national-selection-col { display: table-cell; }
    .national-selection-mode .national-actions-col { display: none; }
    .national-import-progress { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 1rem; background: rgba(15, 23, 42, .58); backdrop-filter: blur(4px); }
    .national-import-progress[hidden] { display: none; }
    .national-import-progress-card { width: min(430px, 100%); border-radius: 18px; background: #fff; box-shadow: 0 24px 70px rgba(15, 23, 42, .28); padding: 1.5rem; text-align: center; }
    .national-import-orbit { position: relative; width: 68px; height: 68px; margin: 0 auto 1rem; border: 5px solid #dbeafe; border-top-color: #2563eb; border-radius: 50%; animation: nationalImportSpin .8s linear infinite; }
    .national-import-orbit::after { content: ''; position: absolute; inset: 12px; border: 4px solid #e0f2fe; border-bottom-color: #0ea5e9; border-radius: 50%; animation: nationalImportSpin .65s linear infinite reverse; }
    @keyframes nationalImportSpin { to { transform: rotate(360deg); } }
</style>

<div class="workspace-page national-inventory-page">
    <x-page-header class="mb-3" eyebrow="Head Office workspace" title="National Inventory" description="Maintain stock that is separate from store inventory, sales channels, and stock allocation." icon="fa-earth-asia">
        <x-slot:actions>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#nationalImportModal"><i class="fa-solid fa-file-arrow-up me-1"></i> Import CSV</button>
        </x-slot:actions>
    </x-page-header>

    <nav class="inventory-scope-tabs mb-3" aria-label="Inventory type">
        <a class="btn btn-outline-primary" href="{{ route('products.index', ['hub_id' => $hub->id]) }}"><i class="fa-solid fa-warehouse me-1"></i> Store Inventory</a>
        <a class="btn btn-primary" href="{{ route('national-inventory.index', ['hub_id' => $hub->id]) }}" aria-current="page"><i class="fa-solid fa-earth-asia me-1"></i> National Inventory</a>
    </nav>

    @if($errors->any())
        <div class="alert alert-danger"><strong>National inventory was not changed.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="national-scope-note mb-3">
        <i class="fa-solid fa-circle-info mt-1" aria-hidden="true"></i>
        <span><strong>Separate inventory:</strong> National stock is available only to Admin and Inventory Staff in {{ $hub->name }} mode. It is not included in store sales, transfers, or Stock Allocation.</span>
    </div>

    <div class="national-inventory-card p-3 mb-3">
        <form method="GET" action="{{ route('national-inventory.index') }}" class="row g-2 align-items-end">
            <input type="hidden" name="hub_id" value="{{ $hub->id }}">
            <div class="col-md-8">
                <label class="form-label small fw-semibold" for="nationalSearch">Find a National product</label>
                <input id="nationalSearch" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name, Item ID, barcode, or brand">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary"><i class="fa-solid fa-search me-1"></i> Search</button>
            </div>
        </form>
    </div>

    <form method="GET" action="{{ route('national-inventory.export') }}" class="national-inventory-card overflow-hidden">
        <input type="hidden" name="hub_id" value="{{ $hub->id }}">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 border-bottom">
            <div>
                <h5 class="mb-1 fw-bold"><i class="fa-solid fa-earth-asia text-primary me-2"></i>National Product Stock</h5>
                <div class="small text-muted">{{ number_format($nationalProducts->total()) }} separate National item(s)</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" id="nationalSelectionToggle"><i class="fa-solid fa-square-check me-1" id="nationalSelectionIcon"></i><span id="nationalSelectionLabel">Select Rows Mode</span></button>
                <button type="submit" class="btn btn-outline-primary d-none" id="nationalExportSelected" name="export_all" value="0" disabled><i class="fa-solid fa-file-export me-1"></i> Export Selected</button>
                <button type="submit" class="btn btn-primary" name="export_all" value="1"><i class="fa-solid fa-file-csv me-1"></i> Export All</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 national-inventory-table" id="nationalInventoryTable">
                <thead><tr><th class="text-center national-selection-col" style="width:48px"><input type="checkbox" class="form-check-input" id="nationalSelectAll" aria-label="Select all National products on this page"></th><th>Product</th><th>Item ID</th><th>Barcode</th><th>Brand</th><th>Unit Type</th><th class="text-center">Stock</th><th>Status</th><th class="text-end national-actions-col">Actions</th></tr></thead>
                <tbody>
                    @forelse($nationalProducts as $product)
                    <tr>
                        <td class="text-center national-selection-col"><input type="checkbox" class="form-check-input national-product-checkbox" name="product_ids[]" value="{{ $product->id }}" aria-label="Select {{ $product->name }}"></td>
                        <td><div class="fw-semibold">{{ $product->name }}</div><small class="text-muted">{{ $product->description ?: 'No description' }}</small></td>
                        <td>{{ $product->item_id }}</td>
                        <td>{{ $product->barcode ?: '—' }}</td>
                        <td>{{ $product->brand ?: '—' }}</td>
                        <td>{{ $product->unit_type ?: '—' }}</td>
                        <td class="text-center fw-bold">{{ number_format($product->stock) }}</td>
                        <td><span class="badge {{ $product->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($product->status) }}</span></td>
                        <td class="text-end national-actions-col">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" title="Edit National product" data-bs-toggle="modal" data-bs-target="#editNationalProductModal{{ $product->id }}"><i class="fa-solid fa-pen-to-square"></i></button>
                                <button type="button" class="btn btn-sm {{ $product->status === 'active' ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $product->status === 'active' ? 'Deactivate' : 'Activate' }} National product" onclick="document.getElementById('toggle-national-product-{{ $product->id }}').submit()"><i class="fa-solid {{ $product->status === 'active' ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i></button>
                                @if(auth()->user()->role === 'admin')
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete National product" onclick="if (confirm('Delete this National product?')) document.getElementById('delete-national-product-{{ $product->id }}').submit()"><i class="fa-solid fa-trash-can"></i></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="py-5 text-center text-muted">No National inventory products found. Import a CSV to begin.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-top">{{ $nationalProducts->links() }}</div>
    </form>
</div>

@foreach($nationalProducts as $product)
    <form id="toggle-national-product-{{ $product->id }}" method="POST" action="{{ route('national-inventory.toggle', $product) }}" class="d-none">
        @csrf @method('PATCH')
        <input type="hidden" name="hub_id" value="{{ $hub->id }}">
    </form>
    @if(auth()->user()->role === 'admin')
        <form id="delete-national-product-{{ $product->id }}" method="POST" action="{{ route('national-inventory.destroy', $product) }}" class="d-none">
            @csrf @method('DELETE')
            <input type="hidden" name="hub_id" value="{{ $hub->id }}">
        </form>
    @endif

    <div class="modal fade" id="editNationalProductModal{{ $product->id }}" tabindex="-1" aria-labelledby="editNationalProductModalLabel{{ $product->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('national-inventory.update', $product) }}">
                @csrf @method('PUT')
                <input type="hidden" name="hub_id" value="{{ $hub->id }}">
                <div class="modal-header">
                    <div><div class="small text-primary fw-bold text-uppercase">National Inventory</div><h5 class="modal-title" id="editNationalProductModalLabel{{ $product->id }}">Edit {{ $product->name }}</h5></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-semibold">Item ID</label><input type="text" name="item_id" value="{{ $product->item_id }}" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Name</label><input type="text" name="name" value="{{ $product->name }}" class="form-control" required></div>
                        <div class="col-12"><label class="form-label fw-semibold">Description</label><textarea name="description" rows="2" class="form-control">{{ $product->description }}</textarea></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Barcode</label><input type="text" name="barcode" value="{{ $product->barcode }}" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Brand</label><input type="text" name="brand" value="{{ $product->brand }}" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Stock</label><input type="number" name="stock" value="{{ $product->stock }}" min="0" step="1" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Unit Type</label><input type="text" name="unit_type" value="{{ $product->unit_type }}" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
            </form>
        </div>
    </div>
@endforeach

<div class="modal fade" id="nationalImportModal" tabindex="-1" aria-labelledby="nationalImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('national-inventory.import') }}" enctype="multipart/form-data" id="nationalImportForm">
            @csrf
            <input type="hidden" name="hub_id" value="{{ $hub->id }}">
            <div class="modal-header">
                <div><div class="small text-primary fw-bold text-uppercase">National Inventory</div><h5 class="modal-title" id="nationalImportModalLabel">Import CSV</h5></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="nationalCsvFile" class="form-label fw-semibold">National product CSV</label>
                <input id="nationalCsvFile" type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                <div class="form-text mt-2">CSV columns: ID, Item ID, Name, Description, Barcode, Brand, Stock, and Unit Type. Existing Item IDs are updated; new Item IDs are created.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="nationalImportSubmit"><i class="fa-solid fa-file-arrow-up me-1"></i> Import National Inventory</button>
            </div>
        </form>
    </div>
</div>

<div class="national-import-progress" id="nationalImportProgress" role="status" aria-live="polite" aria-modal="true" hidden>
    <div class="national-import-progress-card">
        <div class="national-import-orbit" aria-hidden="true"></div>
        <h5 class="fw-bold mb-2">Importing National inventory</h5>
        <p class="text-muted mb-3" id="nationalImportStatus">Uploading and reading the CSV file...</p>
        <div class="progress" style="height: 9px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated w-100" aria-label="Import in progress"></div>
        </div>
        <small class="d-block text-muted mt-3">Keep this page open while every row is validated and saved.</small>
    </div>
</div>

@push('scripts')
<script>
    (() => {
        const table = document.getElementById('nationalInventoryTable');
        const toggle = document.getElementById('nationalSelectionToggle');
        const label = document.getElementById('nationalSelectionLabel');
        const icon = document.getElementById('nationalSelectionIcon');
        const exportSelected = document.getElementById('nationalExportSelected');
        const selectAll = document.getElementById('nationalSelectAll');
        const checkboxes = [...document.querySelectorAll('.national-product-checkbox')];
        let selectionMode = false;

        const updateSelection = () => {
            const selectedCount = checkboxes.filter(checkbox => checkbox.checked).length;
            exportSelected.disabled = selectedCount === 0;
            exportSelected.innerHTML = `<i class="fa-solid fa-file-export me-1"></i> Export Selected${selectedCount ? ` (${selectedCount})` : ''}`;
            if (selectAll) selectAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
        };

        toggle?.addEventListener('click', () => {
            selectionMode = !selectionMode;
            table?.classList.toggle('national-selection-mode', selectionMode);
            exportSelected?.classList.toggle('d-none', !selectionMode);
            label.textContent = selectionMode ? 'Exit Selection Mode' : 'Select Rows Mode';
            icon.className = selectionMode ? 'fa-solid fa-square-xmark me-1' : 'fa-solid fa-square-check me-1';
            if (!selectionMode) {
                checkboxes.forEach(checkbox => checkbox.checked = false);
                if (selectAll) selectAll.checked = false;
            }
            updateSelection();
        });

        selectAll?.addEventListener('change', function () {
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
            updateSelection();
        });
        checkboxes.forEach(checkbox => checkbox.addEventListener('change', updateSelection));

        const importForm = document.getElementById('nationalImportForm');
        const importProgress = document.getElementById('nationalImportProgress');
        const importStatus = document.getElementById('nationalImportStatus');
        const importSubmit = document.getElementById('nationalImportSubmit');
        importForm?.addEventListener('submit', () => {
            if (!importForm.checkValidity()) return;
            importSubmit.disabled = true;
            importProgress.hidden = false;
            const messages = ['Uploading and reading the CSV file...', 'Validating Item IDs, stock, and barcodes...', 'Saving National inventory items...', 'Finalizing the import log...'];
            let messageIndex = 0;
            window.setInterval(() => {
                messageIndex = (messageIndex + 1) % messages.length;
                importStatus.textContent = messages[messageIndex];
            }, 1400);
        });
    })();
</script>
@endpush
@endsection
