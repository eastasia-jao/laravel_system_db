<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->unsignedInteger('replacement_quantity')->nullable()->after('quantity');
            $table->decimal('price_adjustment', 12, 2)->default(0)->after('replacement_unit_price');
            $table->string('status')->default('pending')->after('reason')->index();
            $table->foreignId('reviewed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');
        });

        DB::table('product_replacements')->update([
            'replacement_quantity' => DB::raw('quantity'),
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['replacement_quantity', 'price_adjustment', 'status', 'reviewed_at', 'rejection_reason']);
        });
    }
};
