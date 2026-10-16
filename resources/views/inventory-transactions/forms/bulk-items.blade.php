@php
    $bulkProducts = $products->map(fn ($product) => $product->only(['id', 'item_id', 'barcode', 'name', 'description', 'stock']))->values();
@endphp
@php
    $eventWorkbook = $type === 'sponsor_workshop';
    $inventoryWorkbook = in_array($type, ['stock_transfer', 'branch_transfer', 'restock'], true);
    $serverWorkbook = $eventWorkbook || $inventoryWorkbook;
    $workbookImportUrl = $eventWorkbook
        ? route('inventory-transactions.sponsor.worksheet.import')
        : ($inventoryWorkbook ? route('inventory-transactions.items-workbook.import') : null);
    $workbookExportUrl = $eventWorkbook
        ? route('inventory-transactions.sponsor.worksheet.export')
        : ($inventoryWorkbook ? route('inventory-transactions.items-workbook.export') : null);
    $workbookDownloadUrl = $eventWorkbook
        ? route('inventory-transactions.sponsor.worksheet')
        : ($inventoryWorkbook ? route('inventory-transactions.items-workbook') : null);
@endphp
<script src="{{ asset('js/product-suggestions.js') }}?v=20260918-1439"></script>
<style>
    @keyframes transaction-item-review-blink {
        0%, 100% { background-color: transparent; box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        50% { background-color: rgba(220, 53, 69, 0.1); box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.5); }
    }
    .transaction-item-review-highlight {
        border-radius: 0.375rem;
        animation: transaction-item-review-blink 0.5s ease-in-out 4;
    }
    .transaction-import-overlay {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: grid;
        place-items: center;
        padding: 1rem;
        background: rgba(15, 23, 42, .48);
        backdrop-filter: blur(4px);
    }
    .transaction-import-overlay[hidden] { display: none; }
    .transaction-import-card {
        width: min(430px, 100%);
        padding: 1.5rem;
        border: 1px solid var(--ac-border, #e2e8f0);
        border-radius: 18px;
        background: var(--ac-surface, #fff);
        box-shadow: 0 24px 70px rgba(15, 23, 42, .24);
        text-align: center;
    }
    .transaction-import-spinner {
        width: 54px;
        height: 54px;
        margin: 0 auto 1rem;
        border: 4px solid var(--ac-primary-soft, #eff6ff);
        border-top-color: var(--ac-primary, #2563eb);
        border-radius: 50%;
        animation: transaction-import-spin .8s linear infinite;
    }
    @keyframes transaction-import-spin { to { transform: rotate(360deg); } }
    .transaction-import-progress-indeterminate { overflow: hidden; }
    .transaction-import-progress-indeterminate .progress-bar {
        width: 38% !important;
        background-image: none;
        animation: transaction-import-progress-slide 1.1s ease-in-out infinite;
    }
    @keyframes transaction-import-progress-slide {
        from { transform: translateX(-110%); }
        to { transform: translateX(270%); }
    }
    @media (prefers-reduced-motion: reduce) {
        .transaction-item-review-highlight { animation: none; outline: 3px solid rgba(220, 53, 69, 0.65); }
        .transaction-import-spinner,
        .transaction-import-progress-indeterminate .progress-bar { animation-duration: 2.5s; }
    }
</style>
<div id="transactionBulk" class="mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" data-bulk="import"
            @if($serverWorkbook) data-bulk-import-url="{{ $workbookImportUrl }}" @endif>
            {{ $inventoryWorkbook ? 'Import Items (Excel)' : ($eventWorkbook ? 'Import Items (Excel / CSV)' : 'Import Items (CSV)') }}
        </button>
        @if($serverWorkbook)
            <a class="btn btn-outline-secondary btn-sm"
                href="{{ $workbookDownloadUrl }}?hub_id={{ $hubId }}&type={{ $type }}"
                data-bulk-worksheet-url="{{ $workbookDownloadUrl }}">Download Excel Worksheet</a>
        @else
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('inventory-transactions.product-worksheet', ['hub_id' => $hubId, 'type' => $type]) }}">Download CSV Worksheet</a>
        @endif
        <button type="button" class="btn btn-outline-success btn-sm" data-bulk="export"
            @if($serverWorkbook) data-bulk-export-url="{{ $workbookExportUrl }}" @endif>
            {{ $inventoryWorkbook ? 'Export Items (Excel)' : 'Export Selected Items' }}
        </button>
        <input type="file" accept="{{ $inventoryWorkbook ? '.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : ($eventWorkbook ? '.csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : '.csv,text/csv') }}" class="d-none" id="transactionCsv">
    </div>
    @if($eventWorkbook)
        <small class="text-muted d-block mt-2">For each row with an actual pull-out quantity, enter Stock Condition as Good or Damaged in Excel or CSV.</small>
    @endif
    <div id="transactionImportLoading" class="transaction-import-overlay" role="status" aria-live="polite" hidden>
        <div class="transaction-import-card">
            <div class="transaction-import-spinner" aria-hidden="true"></div>
            <p class="text-primary small fw-bold text-uppercase mb-2">Product Import</p>
            <h2 class="h5 fw-bold mb-2">Getting your items ready</h2>
            <p id="transactionImportLoadingText" class="text-muted mb-3">Preparing your {{ $inventoryWorkbook ? 'Excel' : ($eventWorkbook ? 'Excel / CSV' : 'CSV') }} import...</p>
            <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
                <span>Import progress</span>
                <span id="transactionImportProgressText" class="fw-semibold text-primary">1%</span>
            </div>
            <div id="transactionImportProgress" class="progress" style="height: 9px;" role="progressbar" aria-label="Item import progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="1">
                <div id="transactionImportProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 1%; transition: width .12s linear;"></div>
            </div>
            <small class="d-block text-muted mt-3">Please keep this page open while your product list is loaded.</small>
        </div>
    </div>
    <div id="transactionBulkMessage" role="alert" class="mt-2" style="white-space: pre-line; max-height: 240px; overflow: auto;"></div>
</div>
@push('scripts')
<script type="module">
    import { setupBulkItems } from {{ Illuminate\Support\Js::from(asset('js/transaction-bulk.js?v=20261015-excel-workbooks')) }};
    setupBulkItems({ products: {{ Illuminate\Support\Js::from($bulkProducts) }}, type: {{ Illuminate\Support\Js::from($type) }}, hubCode: {{ Illuminate\Support\Js::from($selectedHub?->code ?? $hubId) }}, endpoint: {{ Illuminate\Support\Js::from(route('hub.products.search.ajax', $hubId ?? 0)) }}, transferDirection: {{ Illuminate\Support\Js::from($type === 'stock_transfer' ? ($transferDirection ?? 'ho_to_branch') : null) }}, worksheetUrl: {{ Illuminate\Support\Js::from($workbookDownloadUrl) }}, isActive: () => document.getElementById('activityType')?.value !== 'fully_booked' });
</script>
@endpush
