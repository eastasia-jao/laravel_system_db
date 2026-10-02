<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $disk = Storage::disk('local');
        if ($disk->directoryExists('chat-attachments')) {
            $disk->deleteDirectory('chat-attachments');

            if ($disk->directoryExists('chat-attachments')) {
                throw new RuntimeException('Unable to remove stored team message attachments.');
            }
        }

        Schema::dropIfExists('direct_messages');

        if (Schema::hasColumn('users', 'last_seen_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('last_seen_at'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'last_seen_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('last_seen_at')->nullable());
        }

        if (! Schema::hasTable('direct_messages')) {
            Schema::create('direct_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
                $table->uuid('client_id');
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_name')->nullable();
                $table->unsignedBigInteger('attachment_size')->nullable();
                $table->timestamps();
                $table->unique(['sender_id', 'client_id']);
                $table->index(['sender_id', 'recipient_id', 'id']);
                $table->index(['recipient_id', 'read_at']);
            });
        }
    }
};
