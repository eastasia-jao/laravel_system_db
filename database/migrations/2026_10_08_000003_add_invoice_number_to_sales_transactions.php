<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_transactions', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('order_number');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_transactions', 'invoice_number')) {
            Schema::table('sales_transactions', fn (Blueprint $table) => $table->dropColumn('invoice_number'));
        }
    }
};
