<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A teaching group's weekly timetable. Students join a group through
        // their `group` name, so existing records need no changes.
        Schema::create('class_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32)->unique();
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->json('days');
            $table->unsignedSmallInteger('late_after_minutes')->default(10);
            $table->timestamps();
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 16)->default('android');
            $table->timestamps();
        });

        // Automatic absences are recorded by the system, not a staff member.
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('class_groups');
    }
};
