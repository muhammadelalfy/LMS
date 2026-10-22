<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32)->default('course');
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('summary', 500)->nullable();
            $table->text('description')->nullable();
            $table->json('goals')->nullable();
            $table->string('target_audience', 500)->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('visible_from')->nullable();
            $table->timestamp('visible_until')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('season_name', 100)->nullable();
            $table->string('sequencing_mode', 40)->default('open');
            $table->string('outline_style', 20)->default('subjects');
            $table->string('pricing', 10)->default('free');
            // Whole currency units, like payments.amount.
            $table->unsignedInteger('price_amount')->nullable();
            $table->unsignedInteger('discounted_price_amount')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'kind', 'id']);
            $table->index(['is_featured', 'status']);
        });

        Schema::create('course_instructors', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['course_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('course_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'position']);
        });

        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('course_subjects')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('summary')->nullable();
            $table->json('key_points')->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->string('video_url', 500)->nullable();
            // A live lesson: when it meets and how to join (a Meet or Zoom link).
            $table->timestamp('live_at')->nullable();
            $table->string('live_provider', 20)->nullable();
            $table->string('live_link', 500)->nullable();
            $table->unsignedInteger('position');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['subject_id', 'position']);
        });

        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // A paid course is opened by this payment being paid.
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'student_id']);
            $table->index('student_id');
        });

        Schema::create('course_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->unique(['lesson_id', 'student_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lesson_progress');
        Schema::dropIfExists('course_enrollments');
        Schema::dropIfExists('course_lessons');
        Schema::dropIfExists('course_subjects');
        Schema::dropIfExists('course_instructors');
        Schema::dropIfExists('courses');
    }
};
