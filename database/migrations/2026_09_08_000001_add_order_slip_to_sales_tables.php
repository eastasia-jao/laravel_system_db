<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pending_sales', 'sales_transactions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('order_slip')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['pending_sales', 'sales_transactions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('order_slip'));
        }
    }
};
