<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_file_requests', function (Blueprint $table) {
            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('created_count')->nullable();
            $table->unsignedInteger('updated_count')->nullable();
            $table->unsignedInteger('skipped_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_file_requests', function (Blueprint $table) {
            $table->dropColumn(['total_rows', 'created_count', 'updated_count', 'skipped_count']);
        });
    }
};
