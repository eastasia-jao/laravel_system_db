<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_sales', function (Blueprint $table) {
            $table->json('quotation_proofs')->nullable();
            $table->json('walkin_payment_proofs')->nullable();
        });
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->json('quotation_proofs')->nullable();
            $table->json('walkin_payment_proofs')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pending_sales', fn (Blueprint $table) => $table->dropColumn(['quotation_proofs', 'walkin_payment_proofs']));
        Schema::table('sales_transactions', fn (Blueprint $table) => $table->dropColumn(['quotation_proofs', 'walkin_payment_proofs']));
    }
};
