@extends('layouts.app') {{-- Adjust to your layout name if needed --}}

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>MAIN BRANCH - Wholesale Sales Report</h2>
        <a href="{{ route('hub.report', $storeHubId) }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to General Report
        </a>
    </div>

    @if(isset($groupedByMonth) && count($groupedByMonth) > 0)
        @foreach($groupedByMonth as $monthName => $monthlyTransactions)
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-header bg-secondary text-white fw-bold py-3">
                    <i class="fa-regular fa-calendar-days me-2"></i> {{ $monthName }}
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle mb-0 text-nowrap" style="font-size: 13px;">
                            <thead class="table-light text-uppercase">
                                <tr>
                                    <th>Company / Customer Name</th>
                                    <th>Address</th>
                                    <th>Date of Purchase</th>
                                    <th>Invoice #</th>
                                    <th>Mode of Payment</th>
                                    <th>Bank Name</th>
                                    <th>Check #</th>
                                    <th>Check Date</th>
                                    <th>Delivery Date</th>
                                    <th>Courier</th>
                                    <th>Withholding Tax (%)</th>
                                    <th>Withholding Tax Amount</th>
                                    <th class="text-center">Payment Status</th>
                                    <th class="text-center">Order Status</th>
                                    <th class="text-end">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($monthlyTransactions as $trx)
                                    @php
                                        // Clean extraction mapping directly to actual database columns
                                        $paymentMode = $trx->mode_of_payment ?? $trx->payment_mode ?? '-';
                                        $bankName = $trx->bank_name ?? ($trx->custom_bank_name ?? '-');
                                        $checkNumber = $trx->check_number ?? '-';
                                        $checkDate = $trx->check_date ? \Carbon\Carbon::parse($trx->check_date)->format('F d, Y') : '-';
                                        
                                        $deliveryDate = $trx->delivery_date ? \Carbon\Carbon::parse($trx->delivery_date)->format('F d, Y') : '-';
                                        $courierName = $trx->courier ?? '-';
                                        
                                        $wTaxPercent = $trx->withholding_tax ?? 0;
                                        $wTaxAmount = $trx->withholding_tax_amount ?? 0;
                                        
                                        $rowTotal = $trx->grand_total > 0 ? $trx->grand_total : ($trx->total_amount > 0 ? $trx->total_amount : optional($trx->items)->sum('line_total'));
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold text-dark">{{ $trx->customer_name }}</td>
                                        <td class="text-muted" style="max-width: 200px; white-space: normal;">{{ $trx->address ?? '-' }}</td>
                                        <td>{{ optional($trx->order_date)->format('F d, Y') ?? '-' }}</td>
                                        <td><span class="font-monospace text-primary fw-bold">{{ $trx->order_number }}</span></td>
                                        <td>{{ $paymentMode }}</td>
                                        <td>{{ $bankName }}</td>
                                        <td><span class="font-monospace">{{ $checkNumber }}</span></td>
                                        <td>{{ $checkDate }}</td>
                                        <td>
                                            @if(!empty($trx->delivery_date))
                                                {{ \Carbon\Carbon::parse($trx->delivery_date)->format('F d, Y') }}
                                            @elseif(!empty($trx->date_of_arrangement))
                                                {{ \Carbon\Carbon::parse($trx->date_of_arrangement)->format('F d, Y') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                       <td>
                                            {{ $trx->courier ?? $trx->courier_name ?? $trx->shipping_courier ?? '-' }}
                                        </td>
                                        <td class="text-center">{{ number_format($wTaxPercent, 2) }}%</td>
                                        <td class="text-danger">₱{{ number_format($wTaxAmount, 2) }}</td>
                                        <td class="text-center"><span class="badge bg-success">PAID</span></td>
                                        <td class="text-center"><span class="badge bg-primary">DELIVERED</span></td>
                                        <td class="fw-bold text-success text-end">₱{{ number_format($rowTotal, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="14" class="text-end text-uppercase">Monthly Subtotal:</td>
                                    <td class="text-success fs-6 text-end">
                                        ₱{{ number_format($monthlyTransactions->sum(function($t) {
                                            return $t->grand_total > 0 ? $t->grand_total : ($t->total_amount > 0 ? $t->total_amount : optional($t->items)->sum('line_total'));
                                        }), 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <div class="alert alert-info text-center py-4 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-info me-2"></i> No wholesale transactions found for this report period.
        </div>
    @endif
</div>
@endsection