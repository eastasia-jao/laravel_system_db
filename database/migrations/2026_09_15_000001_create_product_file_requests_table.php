<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_file_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_hub_id')->constrained('store_hubs');
            $table->foreignId('submitted_by')->constrained('users');
            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('file_name')->nullable();
            $table->longText('csv')->nullable();
            $table->json('product_ids')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'store_hub_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_file_requests');
    }
};
