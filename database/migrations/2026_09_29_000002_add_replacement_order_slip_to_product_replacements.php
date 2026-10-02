<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->string('replacement_order_slip')->nullable()->after('exchange_payment_proofs');
        });
    }

    public function down(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->dropColumn('replacement_order_slip');
        });
    }
};
