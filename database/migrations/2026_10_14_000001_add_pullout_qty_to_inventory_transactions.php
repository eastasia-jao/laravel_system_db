<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_transactions', 'pullout_qty')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->unsignedInteger('pullout_qty')->nullable()->after('quantity');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_transactions', 'pullout_qty')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->dropColumn('pullout_qty');
            });
        }
    }
};
