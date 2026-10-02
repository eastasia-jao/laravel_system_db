<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->uuid('exchange_reference')->nullable()->after('id')->index();
            $table->decimal('exchange_credit', 12, 2)->default(0)->after('price_adjustment');
            $table->decimal('exchange_total', 12, 2)->default(0)->after('exchange_credit');
            $table->decimal('additional_payment_due', 12, 2)->default(0)->after('exchange_total');
        });
    }

    public function down(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->dropIndex(['exchange_reference']);
            $table->dropColumn(['exchange_reference', 'exchange_credit', 'exchange_total', 'additional_payment_due']);
        });
    }
};
