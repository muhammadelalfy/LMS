<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per group per lesson day once its "starts soon" reminder
        // went out, so the sweep that runs every few minutes sends it once.
        Schema::create('session_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_group_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->timestamps();
            $table->unique(['class_group_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_reminders');
    }
};
