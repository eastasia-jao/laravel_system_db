<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_sales', function (Blueprint $table) {
            $table->decimal('shipping_service_fee', 12, 2)->default(0);
            $table->decimal('sales_after_transaction_fee', 12, 2)->nullable();
            $table->decimal('refund_shipping_fee', 12, 2)->default(0);
            $table->decimal('proof_amount', 12, 2)->nullable();
            $table->date('drop_off_date')->nullable();
            $table->text('note')->nullable();
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->decimal('refund_shipping_fee', 12, 2)->default(0);
            $table->decimal('proof_amount', 12, 2)->nullable();
            $table->date('drop_off_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pending_sales', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_service_fee',
                'sales_after_transaction_fee',
                'refund_shipping_fee',
                'proof_amount',
                'drop_off_date',
                'note',
            ]);
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn(['refund_shipping_fee', 'proof_amount', 'drop_off_date']);
        });
    }
};
