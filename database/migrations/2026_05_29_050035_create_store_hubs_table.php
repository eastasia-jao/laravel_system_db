<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_hubs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., "AC Cubao"
            $table->string('code')->unique(); // e.g., "ac-cubao"
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_hubs');
    }
};
