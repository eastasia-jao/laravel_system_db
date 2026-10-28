<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fully_booked_orders', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('store_name');
            $table->string('contact_number', 50)->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('fully_booked_orders', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'contact_number']);
        });
    }
};
