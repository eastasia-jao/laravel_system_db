<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->decimal('wholesale_refund_amount', 10, 2)->default(0);
            $table->decimal('refund_original_order_value', 10, 2)->nullable();
            $table->decimal('refund_original_collected', 10, 2)->nullable();
            $table->decimal('refund_original_withholding_tax', 10, 2)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedBigInteger('refunded_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'wholesale_refund_amount',
                'refund_original_order_value',
                'refund_original_collected',
                'refund_original_withholding_tax',
                'refunded_at',
                'refunded_by',
            ]);
        });
    }
};
