<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One voice or video call on the built-in (LiveKit) media server: a
        // direct call between two people, or a group's online class.
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('started_by')->constrained('users')->cascadeOnDelete();
            $table->string('media', 8); // audio | video
            $table->string('room', 64)->unique();
            $table->string('state', 8)->default('ringing'); // ringing active ended declined missed
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'state']);
        });

        Schema::create('call_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('state', 8)->default('invited'); // invited joined declined left missed
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->unique(['call_id', 'user_id']);
        });

        // A teacher's or manager's connected Google or Zoom account, used to
        // create meeting links. Tokens are encrypted by the model.
        Schema::create('provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 8); // google | zoom
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'provider']);
        });

        // How a group meets online on one day: in the app, or by Meet or Zoom link.
        Schema::create('online_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('class_groups')->cascadeOnDelete();
            $table->date('day');
            $table->string('provider', 8); // builtin | meet | zoom
            $table->string('link', 500)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_sessions');
        Schema::dropIfExists('provider_accounts');
        Schema::dropIfExists('call_participants');
        Schema::dropIfExists('calls');
    }
};
