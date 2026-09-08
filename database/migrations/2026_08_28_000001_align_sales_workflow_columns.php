<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_transactions', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('sales_transactions', 'status')) {
                $table->string('status')->default('completed');
            }
        });

        Schema::table('pending_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pending_sales', 'custom_mop')) {
                $table->string('custom_mop')->nullable();
            }

            if (! Schema::hasColumn('pending_sales', 'bank_name')) {
                $table->string('bank_name')->nullable();
            }

            if (! Schema::hasColumn('pending_sales', 'custom_bank_name')) {
                $table->string('custom_bank_name')->nullable();
            }

            if (! Schema::hasColumn('pending_sales', 'check_number')) {
                $table->string('check_number')->nullable();
            }

            if (! Schema::hasColumn('pending_sales', 'shipping_fee_type')) {
                $table->string('shipping_fee_type')->nullable();
            }

            if (! Schema::hasColumn('pending_sales', 'shipping_fee_amount')) {
                $table->decimal('shipping_fee_amount', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('pending_sales', 'additional_discount_percentage')) {
                $table->decimal('additional_discount_percentage', 5, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pending_sales', function (Blueprint $table) {
            $table->dropColumn([
                'custom_mop',
                'bank_name',
                'custom_bank_name',
                'check_number',
                'shipping_fee_type',
                'shipping_fee_amount',
                'additional_discount_percentage',
            ]);
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('status');
        });
    }
};
