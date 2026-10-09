<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('national_products', function (Blueprint $table) {
            $table->string('status')->default('active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('national_products', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
        Schema::table('national_products', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
