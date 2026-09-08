<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('pending_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pending_sales', 'date_of_arrangement')) {
                $table->date('date_of_arrangement')->nullable()->after('placed_order_date');
            }
            if (! Schema::hasColumn('pending_sales', 'location')) {
                $table->string('location')->nullable()->after('delivery_address');
            }
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_transactions', 'date_of_arrangement')) {
                $table->date('date_of_arrangement')->nullable()->after('order_date');
            }
            if (! Schema::hasColumn('sales_transactions', 'location')) {
                $table->string('location')->nullable()->after('address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pending_sales', function (Blueprint $table) {
            $table->dropColumn(['date_of_arrangement', 'location']);
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn(['date_of_arrangement', 'location']);
        });
    }
};
