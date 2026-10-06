@php
    $money = $money ?? true;
    $normalizedLabel = strtolower($label);
    $icon = match (true) {
        str_contains($normalizedLabel, 'discount') => 'fa-tag',
        str_contains($normalizedLabel, 'shipping') => 'fa-truck',
        str_contains($normalizedLabel, 'refund') => 'fa-money-bill-transfer',
        str_contains($normalizedLabel, 'return') => 'fa-rotate-left',
        str_contains($normalizedLabel, 'replacement') => 'fa-arrows-rotate',
        str_contains($normalizedLabel, 'customer') => str_contains($normalizedLabel, 'new') ? 'fa-user-plus' : 'fa-users',
        str_contains($normalizedLabel, 'transaction') || str_contains($normalizedLabel, 'order') => 'fa-receipt',
        str_contains($normalizedLabel, 'item') || str_contains($normalizedLabel, 'purchased') => 'fa-box-open',
        str_contains($normalizedLabel, 'payment') || str_contains($normalizedLabel, 'collected') => 'fa-credit-card',
        str_contains($normalizedLabel, 'outstanding') => 'fa-hourglass-half',
        default => 'fa-chart-line',
    };
@endphp
<div class="{{ $columnClass ?? 'col-sm-6 col-xl-3' }}">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-body d-flex align-items-center gap-3">
            <span class="sales-metric-icon text-{{ $color ?? 'dark' }}" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
            <div class="min-w-0">
                <div class="small text-muted fw-semibold mb-1">{{ $label }}</div>
                <div class="fs-4 fw-bold text-{{ $color ?? 'dark' }}">
                    {{ $money ? '₱'.number_format((float) $value, 2) : number_format((int) $value) }}
                </div>
                @if(!empty($description))
                    <div class="small text-muted mt-1">{{ $description }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
