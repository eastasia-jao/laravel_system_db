<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pending_sales', 'payment_status')) {
                $table->string('payment_status')->default('unpaid');
            }

            if (! Schema::hasColumn('pending_sales', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->default(0);
            }
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_transactions', 'payment_status')) {
                $table->string('payment_status')->default('unpaid');
            }

            if (! Schema::hasColumn('sales_transactions', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('sales_transactions', 'delivery_status')) {
                $table->string('delivery_status')->default('pending');
            }
        });

        DB::table('pending_sales')->whereNull('delivery_status')->update([
            'delivery_status' => 'pending',
        ]);

        // Preserve the statuses that the old report displayed for every
        // already-confirmed transaction before these fields existed.
        DB::table('sales_transactions')->update([
            'payment_status' => 'paid',
            'amount_paid' => DB::raw('grand_total'),
            'delivery_status' => 'delivered',
        ]);
    }

    public function down(): void
    {
        Schema::table('pending_sales', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'amount_paid']);
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'amount_paid', 'delivery_status']);
        });
    }
};
