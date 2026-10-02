<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recover safely when the original migration is recorded as ran but the
        // table was removed during a partial database reset.
        if (! Schema::hasTable('direct_messages')) {
            Schema::create('direct_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
                $table->uuid('client_id');
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->unique(['sender_id', 'client_id']);
                $table->index(['sender_id', 'recipient_id', 'id']);
                $table->index(['recipient_id', 'read_at']);
            });
        }

        foreach ([
            'attachment_path' => fn (Blueprint $table) => $table->string('attachment_path')->nullable(),
            'attachment_name' => fn (Blueprint $table) => $table->string('attachment_name')->nullable(),
            'attachment_size' => fn (Blueprint $table) => $table->unsignedBigInteger('attachment_size')->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('direct_messages', $column)) {
                Schema::table('direct_messages', $definition);
            }
        }
    }
    public function down(): void
    {
        if (! Schema::hasTable('direct_messages')) {
            return;
        }

        $columns = array_values(array_filter(
            ['attachment_path', 'attachment_name', 'attachment_size'],
            fn (string $column) => Schema::hasColumn('direct_messages', $column)
        ));
        if ($columns !== []) {
            Schema::table('direct_messages', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
