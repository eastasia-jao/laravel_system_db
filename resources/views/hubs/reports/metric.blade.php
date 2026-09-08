@php $money = $money ?? true; @endphp
<div class="col-sm-6 col-xl-3">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
            <div class="small text-muted fw-semibold mb-1">{{ $label }}</div>
            <div class="fs-4 fw-bold text-{{ $color ?? 'dark' }}">
                {{ $money ? '₱'.number_format((float) $value, 2) : number_format((int) $value) }}
            </div>
        </div>
    </div>
</div>
