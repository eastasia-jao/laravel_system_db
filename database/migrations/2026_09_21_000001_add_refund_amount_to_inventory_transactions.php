<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_transactions', 'refund_amount')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->decimal('refund_amount', 12, 2)->default(0)->after('quantity');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_transactions', 'refund_amount')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->dropColumn('refund_amount');
            });
        }
    }
};
