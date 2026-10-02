<section class="panel p-3 p-lg-4 h-100">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h3 class="mb-1"><i class="fa-solid fa-music text-dark me-2"></i>TikTok Settlement Overview</h3>
        </div>
        <span class="badge bg-dark">{{ number_format($tiktokOverview['orders']) }} orders</span>
    </div>
    <div class="row g-2">
        <div class="col-6"><div class="rounded border p-3 h-100"><div class="small text-muted">Final sales after transactions</div><div class="fs-5 fw-bold text-success mt-1">₱{{ number_format($tiktokOverview['final_payout'], 2) }}</div></div></div>
        <div class="col-6"><div class="rounded border p-3 h-100"><div class="small text-muted">Payouts entered</div><div class="fs-5 fw-bold mt-1">{{ number_format($tiktokOverview['payout_entered']) }}</div></div></div>
        <div class="col-6"><div class="rounded border p-3 h-100"><div class="small text-muted">Recalculated after returns</div><div class="fs-5 fw-bold text-primary mt-1">{{ number_format($tiktokOverview['recalculated']) }}</div></div></div>
        <div class="col-6"><div class="rounded border p-3 h-100"><div class="small text-muted">Awaiting sales total</div><div class="fs-5 fw-bold text-warning mt-1">{{ number_format($tiktokOverview['awaiting']) }}</div></div></div>
        <div class="col-12"><div class="rounded border p-3 d-flex justify-content-between align-items-center"><span class="small text-muted">Fully returned orders excluded from sales</span><strong class="text-danger">{{ number_format($tiktokOverview['fully_returned']) }}</strong></div></div>
    </div>
</section>
