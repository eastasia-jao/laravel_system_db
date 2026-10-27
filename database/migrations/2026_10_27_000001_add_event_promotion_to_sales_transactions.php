<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->string('event_promotion_type')->nullable()->after('additional_discount_percentage');
            $table->string('event_promotion_value_type')->nullable()->after('event_promotion_type');
            $table->decimal('event_promotion_value', 10, 2)->default(0)->after('event_promotion_value_type');
            $table->decimal('event_promotion_discount_amount', 10, 2)->default(0)->after('event_promotion_value');
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'event_promotion_type',
                'event_promotion_value_type',
                'event_promotion_value',
                'event_promotion_discount_amount',
            ]);
        });
    }
};
