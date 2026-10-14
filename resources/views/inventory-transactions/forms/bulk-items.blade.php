@php
    $bulkProducts = $products->map(fn ($product) => $product->only(['id', 'item_id', 'barcode', 'name', 'description', 'stock']))->values();
@endphp
@php($eventWorkbook = $type === 'sponsor_workshop')
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
    @media (prefers-reduced-motion: reduce) {
        .transaction-item-review-highlight { animation: none; outline: 3px solid rgba(220, 53, 69, 0.65); }
    }
</style>
<div id="transactionBulk" class="mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" data-bulk="import"
            @if($eventWorkbook) data-bulk-import-url="{{ route('inventory-transactions.sponsor.worksheet.import') }}" @endif>
            {{ $eventWorkbook ? 'Import Items (Excel / CSV)' : 'Import Items (CSV)' }}
        </button>
        @if($eventWorkbook)
            <a class="btn btn-outline-secondary btn-sm"
                href="{{ route('inventory-transactions.sponsor.worksheet', ['hub_id' => $hubId]) }}"
                data-bulk-worksheet-url="{{ route('inventory-transactions.sponsor.worksheet') }}">Download Excel Worksheet</a>
        @else
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('inventory-transactions.product-worksheet', ['hub_id' => $hubId, 'type' => $type]) }}">Download CSV Worksheet</a>
        @endif
        <button type="button" class="btn btn-outline-success btn-sm" data-bulk="export"
            @if($eventWorkbook) data-bulk-export-url="{{ route('inventory-transactions.sponsor.worksheet.export') }}" @endif>
            Export Selected Items
        </button>
        <input type="file" accept="{{ $eventWorkbook ? '.csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : '.csv,text/csv' }}" class="d-none" id="transactionCsv">
    </div>
    <div id="transactionImportLoading" class="mt-3" role="status" aria-live="polite" hidden>
        <div class="d-flex justify-content-between align-items-center text-primary small mb-2">
            <span id="transactionImportLoadingText">Preparing your {{ $eventWorkbook ? 'Excel / CSV' : 'CSV' }} import...</span>
            <span id="transactionImportProgressText" class="fw-semibold">1%</span>
        </div>
        <div class="progress" style="height: 8px;" role="progressbar" aria-label="Item import progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="1">
            <div id="transactionImportProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 1%; transition: width .12s linear;"></div>
        </div>
    </div>
    <div id="transactionBulkMessage" role="alert" class="mt-2" style="white-space: pre-line; max-height: 240px; overflow: auto;"></div>
</div>
@push('scripts')
<script type="module">
    import { setupBulkItems } from {{ Illuminate\Support\Js::from(asset('js/transaction-bulk.js?v=20261015-event-pullout-qty')) }};
    setupBulkItems({ products: {{ Illuminate\Support\Js::from($bulkProducts) }}, type: {{ Illuminate\Support\Js::from($type) }}, hubCode: {{ Illuminate\Support\Js::from($selectedHub?->code ?? $hubId) }}, endpoint: {{ Illuminate\Support\Js::from(route('hub.products.search.ajax', $hubId ?? 0)) }}, transferDirection: {{ Illuminate\Support\Js::from($type === 'stock_transfer' ? ($transferDirection ?? 'ho_to_branch') : null) }}, worksheetUrl: {{ Illuminate\Support\Js::from($eventWorkbook ? route('inventory-transactions.sponsor.worksheet') : null) }}, isActive: () => document.getElementById('activityType')?.value !== 'fully_booked' });
</script>
@endpush
