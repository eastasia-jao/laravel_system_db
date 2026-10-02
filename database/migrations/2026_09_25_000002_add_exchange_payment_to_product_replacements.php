<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->decimal('exchange_payment_amount', 12, 2)->default(0)->after('additional_payment_due');
            $table->string('exchange_payment_method')->nullable()->after('exchange_payment_amount');
            $table->string('exchange_payment_reference')->nullable()->after('exchange_payment_method');
            $table->json('exchange_payment_proofs')->nullable()->after('exchange_payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->dropColumn(['exchange_payment_amount', 'exchange_payment_method', 'exchange_payment_reference', 'exchange_payment_proofs']);
        });
    }
};
