<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->string('replacement_shipping_fee_type')->nullable()->after('additional_payment_due');
            $table->decimal('replacement_shipping_fee_amount', 12, 2)->default(0)->after('replacement_shipping_fee_type');
        });
    }

    public function down(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->dropColumn(['replacement_shipping_fee_type', 'replacement_shipping_fee_amount']);
        });
    }
};
