<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingSale extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'items' => 'array',
        'quotation_proofs' => 'array',
        'walkin_payment_proofs' => 'array',
        'placed_order_date' => 'date',
        'date_of_arrangement' => 'date', // Added to ensure proper date casting
        'delivery_date' => 'date',
        'check_date' => 'date',
        'confirmed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'shipping_fee_amount' => 'decimal:2',
        'additional_discount_percentage' => 'decimal:2',
        'withholding_tax' => 'decimal:2',
        'withholding_tax_amount' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'shipping_service_fee' => 'decimal:2',
        'sales_after_transaction_fee' => 'decimal:2',
        'refund_shipping_fee' => 'decimal:2',
        'proof_amount' => 'decimal:2',
        'drop_off_date' => 'date',
    ];

    public function storeHub()
    {
        return $this->belongsTo(StoreHub::class, 'store_hub_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
