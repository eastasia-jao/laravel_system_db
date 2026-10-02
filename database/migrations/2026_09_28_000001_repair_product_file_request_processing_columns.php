<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_file_requests')) {
            return;
        }

        if (! Schema::hasColumn('product_file_requests', 'processing_status')
            || ! Schema::hasColumn('product_file_requests', 'processing_error')) {
            Schema::table('product_file_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('product_file_requests', 'processing_status')) {
                    $table->string('processing_status')->nullable();
                }
                if (! Schema::hasColumn('product_file_requests', 'processing_error')) {
                    $table->text('processing_error')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Keep repaired columns; queued requests may depend on them.
    }
};
