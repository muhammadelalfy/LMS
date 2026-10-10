<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_bank_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('exam_departments')->nullOnDelete();
            $table->enum('type', ['mcq', 'true_false', 'essay', 'math', 'geometry'])->default('mcq');
            $table->string('title')->nullable();
            $table->string('grade')->nullable();
            $table->longText('prompt_html');
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();
            $table->unsignedInteger('points')->default(1);
            $table->string('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'type', 'grade']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_questions');
    }
};
