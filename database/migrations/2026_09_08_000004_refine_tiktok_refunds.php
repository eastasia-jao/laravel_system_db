<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->string('return_condition')->nullable();
            $table->string('refund_status')->default('none');
        });
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->boolean('payout_includes_refunds')->default(false);
        });
        DB::table('transaction_items')->where('return_status', 'received')->update(['return_condition' => 'good']);
        // Preserve previously recorded deductions until staff reconcile them.
        DB::table('transaction_items')->where('customer_refund_amount', '>', 0)->update(['refund_status' => 'completed']);
    }

    public function down(): void
    {
        Schema::table('transaction_items', fn (Blueprint $table) => $table->dropColumn(['return_condition', 'refund_status']));
        Schema::table('sales_transactions', fn (Blueprint $table) => $table->dropColumn('payout_includes_refunds'));
    }
};
