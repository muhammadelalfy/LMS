<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exam_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exam_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('exam_departments')->nullOnDelete();
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->string('title');
            $table->string('grade')->nullable();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->text('instructions')->nullable();
            $table->string('watermark_text')->nullable();
            $table->unsignedTinyInteger('watermark_opacity')->default(12);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();
        });

        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('exam_templates')->cascadeOnDelete();
            $table->enum('type', ['mcq', 'true_false', 'essay', 'math', 'geometry'])->default('mcq');
            $table->longText('prompt_html');
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();
            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('exam_templates')->cascadeOnDelete();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->enum('status', ['ready', 'active', 'submitted', 'flagged', 'expired'])->default('ready');
            $table->boolean('camera_required')->default(true);
            $table->boolean('fullscreen_required')->default(true);
            $table->unsignedInteger('focus_loss_count')->default(0);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();
            $table->unique(['template_id', 'student_id']);
        });

        Schema::create('exam_session_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('exam_questions')->cascadeOnDelete();
            $table->longText('answer')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'question_id']);
        });

        Schema::create('exam_session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->enum('type', ['camera_granted', 'camera_denied', 'fullscreen_entered', 'fullscreen_exited', 'focus_lost', 'focus_restored', 'visibility_hidden', 'visibility_visible', 'submitted', 'heartbeat']);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_session_events');
        Schema::dropIfExists('exam_session_answers');
        Schema::dropIfExists('exam_sessions');
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('exam_templates');
        Schema::dropIfExists('exam_departments');
    }
};
