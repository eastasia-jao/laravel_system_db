<section class="panel marketplace-mop-panel p-3 p-lg-4 h-100">
    <div class="row g-4">
        @foreach($marketplaceOverviews as $overview)
            @php($overviewChannel = $overview['channel'])
            <div class="{{ $marketplaceOverviews->count() > 1 ? 'col-xl-6' : 'col-12' }}">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div><h3 class="mb-1"><i class="fa-solid {{ $overviewChannel === 'shopee' ? 'fa-bag-shopping text-warning' : 'fa-store text-info' }} me-2"></i>{{ ucfirst($overviewChannel) }} Payment Overview</h3><p class="small text-muted mb-0">Month to date · Sales grouped by platform MOP.</p></div>
                    <div class="marketplace-mop-total"><div class="small text-uppercase fw-bold">Total collected</div><div class="fs-5 fw-bold">₱{{ number_format($overview['total'], 2) }}</div></div>
                </div>
                @foreach($overview['rows'] as $row)
                    @if($overview['rows']->count() > 1)<div class="small fw-bold text-muted mb-2">{{ $row['name'] }}</div>@endif
                    <div class="row row-cols-2 row-cols-md-3 g-2 mb-3">
                        @foreach($row['amounts'] as $method => $amount)
                            <div class="col"><div class="marketplace-mop-card h-100"><div class="marketplace-mop-name">{{ $method }}</div><div class="marketplace-mop-amount">₱{{ number_format($amount, 2) }}</div><div class="marketplace-mop-count">{{ number_format($row['counts'][$method]) }} transaction{{ $row['counts'][$method] === 1 ? '' : 's' }}</div></div></div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</section>
