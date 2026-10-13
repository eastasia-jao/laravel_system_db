<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('national_products', function (Blueprint $table) {
            $table->unsignedInteger('damaged_stock')->default(0)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('national_products', function (Blueprint $table) {
            $table->dropColumn('damaged_stock');
        });
    }
};
