<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->string('stock_condition', 20)->default('good')->after('quantity');
        });
        Schema::table('fully_booked_order_items', function (Blueprint $table) {
            $table->string('stock_condition', 20)->default('good')->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn('stock_condition');
        });
        Schema::table('fully_booked_order_items', function (Blueprint $table) {
            $table->dropColumn('stock_condition');
        });
    }
};
