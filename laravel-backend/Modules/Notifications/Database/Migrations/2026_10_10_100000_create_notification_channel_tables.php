<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A verified phone number a person has agreed to be reached on, per
        // paid channel. The row exists after verification; `opted_in_at`
        // says whether that channel is switched on.
        Schema::create('contact_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16); // sms | whatsapp
            $table->string('address', 20); // E.164
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->string('source', 32)->nullable(); // app | stop-reply
            $table->timestamps();
            $table->unique(['user_id', 'channel']);
            $table->index('address');
        });

        // The code sent to a phone to prove it is theirs.
        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // Which channels a person wants for which kind of notice. Only paid
        // channels and push are stored; the in-app inbox is always on.
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->string('channel', 16);
            $table->boolean('enabled');
            $table->timestamps();
            $table->unique(['user_id', 'category', 'channel']);
        });

        Schema::create('notification_settings', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('quiet_from', 5)->nullable(); // HH:MM
            $table->string('quiet_to', 5)->nullable();
            $table->timestamps();
        });

        // One row per notice, recipient and channel: what was tried and what
        // the provider said. `ref` ties it to the inbox notification.
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('ref')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('category', 32);
            $table->string('title');
            $table->text('body');
            $table->string('state', 16)->default('queued'); // queued sent delivered read failed skipped
            $table->string('reason', 64)->nullable();
            $table->string('provider_id')->nullable()->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('send_after')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            // A retried job can never create a second row for the same send.
            $table->unique(['ref', 'user_id', 'channel']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('phone_verifications');
        Schema::dropIfExists('contact_channels');
    }
};
