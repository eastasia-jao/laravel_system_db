<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing columns to pending_sales table
        Schema::table('pending_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pending_sales', 'delivery_date')) {
                $table->date('delivery_date')->nullable()->after('courier');
            }
            if (! Schema::hasColumn('pending_sales', 'check_date')) {
                $table->date('check_date')->nullable()->after('delivery_date');
            }
        });

        // Add missing columns to sales_transactions table
        Schema::table('sales_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_transactions', 'delivery_date')) {
                $table->date('delivery_date')->nullable()->after('check_date');
            }
            if (! Schema::hasColumn('sales_transactions', 'courier')) {
                $table->string('courier')->nullable()->after('delivery_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pending_sales', function (Blueprint $table) {
            $table->dropColumn(['delivery_date', 'check_date']);
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn(['delivery_date', 'courier']);
        });
    }
};
