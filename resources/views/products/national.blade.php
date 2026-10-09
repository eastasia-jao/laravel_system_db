@extends('layouts.app')

@section('content')
<style>
    .national-inventory-page { max-width: 1500px; }
    .national-inventory-card { border: 1px solid var(--ac-border); border-radius: 16px; background: var(--ac-surface); box-shadow: var(--ac-shadow-sm); }
    .national-inventory-table th { white-space: nowrap; }
    .national-inventory-table td { vertical-align: middle; }
    .national-scope-note { display: flex; align-items: flex-start; gap: .55rem; padding: .75rem .9rem; border: 1px solid #bfdbfe; border-radius: 11px; background: #eff6ff; color: #1e40af; font-size: .78rem; }
    .inventory-scope-tabs { display: flex; flex-wrap: wrap; gap: .5rem; }
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

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
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
                <a class="btn btn-outline-secondary" href="{{ route('national-inventory.index', ['hub_id' => $hub->id]) }}">Clear</a>
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
                <button type="submit" class="btn btn-outline-primary" name="export_all" value="0"><i class="fa-solid fa-file-export me-1"></i> Export Selected</button>
                <button type="submit" class="btn btn-primary" name="export_all" value="1"><i class="fa-solid fa-file-csv me-1"></i> Export All</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 national-inventory-table">
                <thead><tr><th class="text-center" style="width:48px"><input type="checkbox" class="form-check-input" id="nationalSelectAll" aria-label="Select all National products on this page"></th><th>Product</th><th>Item ID</th><th>Barcode</th><th>Brand</th><th>Department</th><th>Unit</th><th class="text-end">Retail Price</th><th class="text-center">Stock</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($nationalProducts as $product)
                    <tr>
                        <td class="text-center"><input type="checkbox" class="form-check-input national-product-checkbox" name="product_ids[]" value="{{ $product->id }}" aria-label="Select {{ $product->name }}"></td>
                        <td><div class="fw-semibold">{{ $product->name }}</div><small class="text-muted">{{ $product->description ?: 'No description' }}</small></td>
                        <td>{{ $product->item_id }}</td>
                        <td>{{ $product->barcode ?: '—' }}</td>
                        <td>{{ $product->brand ?: '—' }}</td>
                        <td>{{ $product->retail_department ?: '—' }}</td>
                        <td>{{ $product->unit_type ?: '—' }}</td>
                        <td class="text-end">₱{{ number_format((float) $product->sales_price, 2) }}</td>
                        <td class="text-center fw-bold">{{ number_format($product->stock) }}</td>
                        <td><span class="badge {{ $product->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($product->status) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="py-5 text-center text-muted">No National inventory products found. Import a CSV to begin.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-top">{{ $nationalProducts->links() }}</div>
    </form>
</div>

<div class="modal fade" id="nationalImportModal" tabindex="-1" aria-labelledby="nationalImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('national-inventory.import') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="hub_id" value="{{ $hub->id }}">
            <div class="modal-header">
                <div><div class="small text-primary fw-bold text-uppercase">National Inventory</div><h5 class="modal-title" id="nationalImportModalLabel">Import CSV</h5></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="nationalCsvFile" class="form-label fw-semibold">National product CSV</label>
                <input id="nationalCsvFile" type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                <div class="form-text mt-2">Required columns: Item ID and Name. The exported National CSV can be edited and imported again. Existing Item IDs are updated; new Item IDs are created.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-arrow-up me-1"></i> Import National Inventory</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('nationalSelectAll')?.addEventListener('change', function () {
        document.querySelectorAll('.national-product-checkbox').forEach(checkbox => checkbox.checked = this.checked);
    });
</script>
@endpush
@endsection
