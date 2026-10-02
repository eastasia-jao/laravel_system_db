@extends('layouts.app')
@section('content')
<style>
    .import-preview { max-height: 500px; overflow: auto; }
    .import-preview thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background-color: #fff;
        box-shadow: inset 0 -1px 0 #dee2e6;
    }
</style>
<div class="container-fluid py-3">
    <a href="{{ route('products.index') }}">&larr; List of Products</a>
    <h2 class="mt-3">{{ ucfirst($fileRequest->type) }} request #{{ $fileRequest->id }}</h2>
    @php
        $requestStatus = match ($fileRequest->processing_status) {
            'queued' => 'Approved for processing — queued',
            'processing' => 'Approved for processing — in progress',
            'failed' => 'Processing failed — review required',
            default => $fileRequest->status === 'pending' ? 'Awaiting approval' : ucfirst($fileRequest->status),
        };
    @endphp
    @if(in_array($fileRequest->processing_status, ['queued', 'processing'], true))
        <div class="alert alert-info" role="status">{{ $fileRequest->processing_status === 'queued' ? 'Queued for processing' : 'Processing' }}. You can leave this page and return later. <a href="{{ request()->url() }}">Refresh status</a></div>
        <p class="text-muted">This request is already authorized. Approve and Reject are unavailable once processing is queued. Store Hub imports and exports submitted by Admin or Inventory Staff are authorized automatically; sales associate requests require a staff decision first.</p>
    @elseif($fileRequest->processing_status === 'failed')
        <div class="alert alert-danger">{{ $fileRequest->processing_error }}</div>
    @endif
    <p><strong>{{ $requestStatus }}</strong> · {{ $fileRequest->hub?->name }} · Submitted by {{ $fileRequest->submitter?->name }} on {{ $fileRequest->created_at->format('M d, Y H:i') }}</p>
    @if($fileRequest->reviewed_at)<p>Reviewed by {{ $fileRequest->reviewer?->name }} on {{ $fileRequest->reviewed_at->format('M d, Y H:i') }}.</p>@endif
    @if($fileRequest->rejection_reason)<div class="alert alert-danger">Rejection reason: {{ $fileRequest->rejection_reason }}</div>@endif
    @if($fileRequest->type === 'import')
        <p class="small text-muted">Imports are header-driven. Include only the fields you want to change; omitted names, descriptions, barcodes, classifications, prices, and stock remain unchanged. Branch imports update branch data, while Head Office imports can also update shared product details.</p>
        <p>File: <strong>{{ $fileRequest->file_name }}</strong> · {{ $rowCount }} product rows (10 per page)</p>
        <div class="alert alert-warning">Approval creates or updates these products in this store. The CSV stock value replaces the current stock; it is not added to it.</div>
        <div class="table-responsive import-preview mb-3" tabindex="0" role="region" aria-label="Imported product preview"><table class="table table-sm table-bordered">
            <thead><tr>@foreach($headers as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
            <tbody>@foreach($rows as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody>
        </table></div>
        @if($rowPages->hasPages())
            <div class="mt-3">{{ $rowPages->onEachSide(1)->links() }}</div>
        @endif
    @else
        <p>{{ count($fileRequest->product_ids) }} selected products. The downloadable CSV contains their values at approval time.</p>
        <div class="table-responsive mb-3"><table class="table"><thead><tr><th>Item ID</th><th>Product</th><th>Current stock</th></tr></thead><tbody>
            @foreach($products as $product)<tr><td>{{ $product->item_id }}</td><td>{{ $product->name }}</td><td>{{ $product->stock }}</td></tr>@endforeach
        </tbody></table></div>
        @if($products->hasPages())
            <div class="mt-3">{{ $products->onEachSide(1)->links() }}</div>
        @endif
        @if($fileRequest->status === 'approved')
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-success" href="{{ route('product-file-requests.download', $fileRequest) }}"><i class="fa-solid fa-file-excel me-1"></i>Download Excel</a>
                <a class="btn btn-outline-secondary" href="{{ route('product-file-requests.download-csv', $fileRequest) }}">Download CSV</a>
            </div>
            <p class="small text-muted mt-2 mb-0">Use the Excel download to keep Item IDs and barcodes displayed as complete text. Use CSV when preparing a file for re-import.</p>
        @endif
    @endif
    @can('verify-inventory')
    @if($fileRequest->status === 'pending' && !in_array($fileRequest->processing_status, ['queued', 'processing'], true))
        <div class="card mt-3"><div class="card-body">
            <h5>Inventory staff decision</h5>
            <form action="{{ route('product-file-requests.review', $fileRequest) }}" method="POST" class="mb-3">
                @csrf
                <input type="hidden" name="decision" value="approved">
                <button class="btn btn-success">{{ $fileRequest->type === 'import' ? 'Approve and apply import' : 'Approve and prepare export' }}</button>
            </form>
            <form action="{{ route('product-file-requests.review', $fileRequest) }}" method="POST">
                @csrf
                <input type="hidden" name="decision" value="rejected">
                <label for="rejectionReason" class="form-label">Reason for rejection</label>
                <textarea id="rejectionReason" class="form-control mb-2" name="rejection_reason" maxlength="2000" required>{{ old('rejection_reason') }}</textarea>
                <button class="btn btn-outline-danger">Reject request</button>
            </form>
        </div></div>
    @endif
    @endcan
</div>
@endsection
