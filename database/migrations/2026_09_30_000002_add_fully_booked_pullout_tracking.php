<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fully_booked_orders', function (Blueprint $table) {
            $table->foreignId('pulled_out_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('pulled_out_at')->nullable()->after('reviewed_at');
        });

        Schema::create('fully_booked_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fully_booked_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('item_id')->nullable();
            $table->unsignedInteger('quantity');
            $table->foreignId('inventory_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fully_booked_order_items');

        Schema::table('fully_booked_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pulled_out_by');
            $table->dropColumn('pulled_out_at');
        });
    }
};
