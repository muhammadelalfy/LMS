<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which teacher runs a group. Null (the old default) means any
        // teacher may talk to its students.
        Schema::table('class_groups', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->after('late_after_minutes')->constrained('users')->nullOnDelete();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 8); // direct | group
            $table->foreignId('group_id')->nullable()->unique()->constrained('class_groups')->cascadeOnDelete();
            // "smaller-id:larger-id": one direct conversation per pair.
            $table->string('direct_key', 32)->nullable()->unique();
            $table->boolean('announce_only')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('muted_until')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id(); // grows with time: cursors and ordering use it
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('kind', 8)->default('text'); // text | system
            // The sender's own id for this message: sending it twice stores it once.
            $table->string('client_id', 40)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['conversation_id', 'client_id']);
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('message_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->timestamps();
            $table->unique(['message_id', 'reporter_id']);
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['blocker_id', 'blocked_id']);
        });

        // Every time management reads a conversation it is not part of.
        Schema::create('chat_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_audit_logs');
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('message_reports');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_members');
        Schema::dropIfExists('conversations');
        Schema::table('class_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_id');
        });
    }
};
