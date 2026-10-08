<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->boolean('uses_exchange_credit')->default(true)->after('exchange_credit');
        });
    }

    public function down(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->dropColumn('uses_exchange_credit');
        });
    }
};
