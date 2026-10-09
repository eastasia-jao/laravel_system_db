@extends('layouts.app')
@section('content')
<style>
.transfer-review-page{max-width:1540px;margin:0 auto}.transfer-review-hero{position:relative;overflow:hidden;border-radius:22px;padding:1.75rem 2rem;color:#fff;background:linear-gradient(135deg,#172554,#1d4ed8 62%,#38bdf8);box-shadow:0 14px 34px rgba(37,99,235,.2)}.transfer-review-hero:after{content:'';position:absolute;width:240px;height:240px;right:-65px;top:-110px;border-radius:50%;background:rgba(255,255,255,.1)}.transfer-review-hero>*{position:relative;z-index:1}.transfer-review-back{color:#dbeafe;text-decoration:none;font-size:.84rem;font-weight:600}.transfer-review-back:hover{color:#fff}.review-stat{height:100%;border:1px solid #e2e8f0;border-radius:15px;padding:1rem 1.1rem;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,.05)}.review-stat-icon{width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;border-radius:11px;background:#eff6ff;color:#2563eb}.review-stat-value{font-size:1.45rem;line-height:1;font-weight:800;color:#0f172a}.transfer-review-panel{border:1px solid #e2e8f0;border-radius:18px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.06);overflow:hidden}.transfer-review-panel-head{padding:1.25rem 1.35rem;border-bottom:1px solid #e2e8f0;background:#f8fafc}#branchTransferRequests .accordion-item{border:0;border-bottom:1px solid #e2e8f0}#branchTransferRequests .accordion-item:last-child{border-bottom:0}#branchTransferRequests .accordion-button{padding:1rem 1.35rem;background:#fff;box-shadow:none}#branchTransferRequests .accordion-button:not(.collapsed){color:inherit;background:#eff6ff}.transfer-request-grid{display:grid;grid-template-columns:minmax(230px,1.1fr) minmax(280px,1.4fr) minmax(120px,.55fr);gap:1rem;align-items:center;width:100%;padding-right:1rem}.transfer-doc-label{color:#64748b;font-size:.67rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.transfer-doc-number{color:#0f172a;font-weight:800;overflow-wrap:anywhere}.transfer-route-display{display:flex;align-items:center;gap:.7rem;min-width:0}.transfer-route-display span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.transfer-route-arrow{width:30px;height:30px;flex:0 0 30px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;color:#2563eb;background:#dbeafe}.transfer-status{justify-self:end;border-radius:999px;padding:.45rem .7rem;font-size:.68rem;letter-spacing:.04em}.transfer-detail-shell{padding:1.35rem;background:#f8fafc}@media(max-width:767.98px){.transfer-review-hero{padding:1.35rem;border-radius:16px}.transfer-request-grid{grid-template-columns:1fr;gap:.55rem}.transfer-status{justify-self:start}.transfer-route-display span{white-space:normal}}
</style>
<div class="container-fluid py-4 transfer-review-page">
    <x-page-header class="mb-4" eyebrow="Inventory approval workspace" title="Stock Transfer Review Requests" description="Review branch-to-branch requests by Transfer Document No. before stock is moved." icon="fa-clipboard-check">
        <x-slot:actions>
            <a class="btn btn-outline-secondary" href="{{ route('products.index') }}"><i class="fa-solid fa-arrow-left me-1"></i> Products</a>
            <a class="btn btn-primary" href="{{ route('inventory-transactions.index', ['type' => 'branch_transfer']) }}"><i class="fa-solid fa-clock-rotate-left me-1"></i> Transfer logs</a>
        </x-slot:actions>
    </x-page-header>
    <div class="row row-cols-2 row-cols-lg-4 g-3 mb-4">
        <div class="col"><div class="review-stat d-flex align-items-center gap-3"><span class="review-stat-icon"><i class="fa-solid fa-file-lines"></i></span><div><div class="review-stat-value">{{ $transferRequestCounts['total'] }}</div><div class="small text-muted">Total documents</div></div></div></div>
        <div class="col"><div class="review-stat d-flex align-items-center gap-3"><span class="review-stat-icon" style="color:#d97706;background:#fffbeb"><i class="fa-solid fa-hourglass-half"></i></span><div><div class="review-stat-value">{{ $transferRequestCounts['pending'] }}</div><div class="small text-muted">Pending review</div></div></div></div>
        <div class="col"><div class="review-stat d-flex align-items-center gap-3"><span class="review-stat-icon" style="color:#059669;background:#ecfdf5"><i class="fa-solid fa-circle-check"></i></span><div><div class="review-stat-value">{{ $transferRequestCounts['approved'] }}</div><div class="small text-muted">Approved</div></div></div></div>
        <div class="col"><div class="review-stat d-flex align-items-center gap-3"><span class="review-stat-icon" style="color:#dc2626;background:#fef2f2"><i class="fa-solid fa-circle-xmark"></i></span><div><div class="review-stat-value">{{ $transferRequestCounts['rejected'] }}</div><div class="small text-muted">Rejected</div></div></div></div>
    </div>

    <section class="transfer-review-panel">
    <div class="transfer-review-panel-head">
        <h5 class="fw-bold mb-1">Transfer documents</h5>
        <p class="small text-muted mb-0">Open a document to inspect its product lines and approve or reject the complete request.</p>
    </div>
    <div class="accordion" id="branchTransferRequests">
        @forelse($transferRequests as $group)
            @php
                $transfer = $group->first();
                $documentNo = $transfer->reference ?: $transfer->transfer_batch_id;
                $collapseId = 'transferRequest'.preg_replace('/[^A-Za-z0-9]/', '', (string) ($transfer->transfer_batch_id ?: $transfer->id));
            @endphp
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}">
                        <span class="transfer-request-grid">
                            <span><span class="transfer-doc-label d-block">Transfer Document No.</span><span class="transfer-doc-number d-block">{{ $documentNo }}</span></span>
                            <span class="transfer-route-display"><span>{{ $transfer->sourceHub?->name }}</span><i class="fa-solid fa-arrow-right transfer-route-arrow"></i><span>{{ $transfer->targetHub?->name }}</span></span>
                            <span class="badge transfer-status {{ $transfer->status === 'approved' ? 'text-bg-success' : ($transfer->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ strtoupper($transfer->status) }}</span>
                        </span>
                    </button>
                </h2>
                <div id="{{ $collapseId }}" class="accordion-collapse collapse" data-bs-parent="#branchTransferRequests">
                    <div class="accordion-body transfer-detail-shell">
                        <div class="row g-2 small mb-3">
                            <div class="col-md-3"><strong>Transfer Document No.</strong><div>{{ $documentNo }}</div></div>
                            <div class="col-md-3"><strong>Requested by</strong><div>{{ $transfer->creator?->name ?? $transfer->creator?->username ?? 'Unknown' }}</div></div>
                            <div class="col-md-3"><strong>Transfer date</strong><div>{{ $transfer->occurred_on?->format('M d, Y') }}</div></div>
                            <div class="col-md-3"><strong>Total quantity</strong><div>{{ $group->sum('quantity') }}</div></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-3">
                                <thead class="table-light"><tr><th>Item ID</th><th>Product</th><th>Description</th><th class="text-end">Quantity</th></tr></thead>
                                <tbody>
                                    @foreach($group as $entry)
                                        <tr>
                                            <td>{{ $entry->product?->item_id ?? '—' }}</td>
                                            <td>{{ $entry->product?->name ?? $entry->product?->catalogProduct?->name ?? 'Unknown product' }}</td>
                                            <td>{{ $entry->product?->description ?? $entry->product?->catalogProduct?->description ?? '—' }}</td>
                                            <td class="text-end fw-bold">{{ $entry->quantity }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($transfer->status === 'pending' && (int) $transfer->created_by !== (int) auth()->id())
                            <div class="d-flex flex-wrap gap-2 align-items-end">
                                <form method="POST" action="{{ route('inventory-transactions.branch-transfer.review', ['batch' => $transfer->transfer_batch_id]) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="approved">
                                    <button class="btn btn-success">Approve and transfer stock</button>
                                </form>
                                <form method="POST" action="{{ route('inventory-transactions.branch-transfer.review', ['batch' => $transfer->transfer_batch_id]) }}" class="d-flex flex-wrap gap-2 align-items-end">
                                    @csrf
                                    <input type="hidden" name="decision" value="rejected">
                                    <div><label class="form-label small mb-1">Rejection reason</label><input name="rejection_reason" class="form-control" maxlength="2000" required></div>
                                    <button class="btn btn-outline-danger">Reject request</button>
                                </form>
                            </div>
                        @elseif($transfer->reviewer)
                            <div class="small text-muted">Reviewed by {{ $transfer->reviewer->name ?? $transfer->reviewer->username }}{{ $transfer->reviewed_at ? ' on '.$transfer->reviewed_at->format('M d, Y h:i A') : '' }}.</div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="border rounded p-4 text-center text-muted">No branch transfer requests yet.</div>
        @endforelse
    </div>
    @if($transferRequests->hasPages())
        <div class="p-3 border-top">{{ $transferRequests->onEachSide(1)->links() }}</div>
    @endif
    </section>

</div>
@endsection
