<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fully_booked_orders', function (Blueprint $table) {
            $table->string('store_name')->nullable()->after('store_hub_id');
        });
    }

    public function down(): void
    {
        Schema::table('fully_booked_orders', function (Blueprint $table) {
            $table->dropColumn('store_name');
        });
    }
};
