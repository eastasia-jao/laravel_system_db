<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('read_at', 'notifications_read_at_idx');
        });

        Schema::table('product_file_requests', function (Blueprint $table) {
            $table->index(['status', 'updated_at'], 'product_file_requests_retention_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_file_requests', function (Blueprint $table) {
            $table->dropIndex('product_file_requests_retention_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_read_at_idx');
        });
    }
};
