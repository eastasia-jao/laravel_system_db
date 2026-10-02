<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->string('exchange_custom_mop')->nullable()->after('exchange_payment_method');
            $table->string('exchange_bank_name')->nullable()->after('exchange_custom_mop');
            $table->string('exchange_custom_bank_name')->nullable()->after('exchange_bank_name');
            $table->string('exchange_check_number')->nullable()->after('exchange_custom_bank_name');
            $table->date('exchange_check_date')->nullable()->after('exchange_check_number');
        });
    }

    public function down(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->dropColumn([
                'exchange_custom_mop',
                'exchange_bank_name',
                'exchange_custom_bank_name',
                'exchange_check_number',
                'exchange_check_date',
            ]);
        });
    }
};
