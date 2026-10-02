<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_file_csv_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_file_request_id')->constrained('product_file_requests')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('content');
            $table->unique(['product_file_request_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_file_csv_chunks');
    }
};
