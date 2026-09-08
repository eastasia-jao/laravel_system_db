<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_hub_id')->nullable()->constrained('store_hubs')->nullOnDelete();
            $table->string('action_type', 50);
            $table->string('description');
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['action_type', 'created_at']);
            $table->index(['store_hub_id', 'created_at']);
        });

        Schema::create('staff_activity_log_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_activity_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_id')->nullable();
            $table->string('product_name');
            $table->string('operation', 30)->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('stock_before')->nullable();
            $table->integer('stock_after')->nullable();
            $table->json('details')->nullable();

            $table->index(['staff_activity_log_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_activity_log_items');
        Schema::dropIfExists('staff_activity_logs');
    }
};
