<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile_number')->nullable();
            $table->unsignedBigInteger('hub_id')->nullable();
            $table->string('employee_id')->unique()->nullable(); // Added
            // $table->string('role')->nullable();                // Added
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_id', 'role', 'mobile_number', 'hub_id']);
        });
    }
};
