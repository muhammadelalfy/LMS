<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE exam_questions MODIFY type ENUM('mcq','true_false','essay','math','geometry') NOT NULL DEFAULT 'mcq'");
            return;
        }

        if ($driver !== 'sqlite') return;

        DB::statement('PRAGMA foreign_keys = OFF');
        Schema::create('exam_questions_geometry', function (Blueprint $table) {
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

        DB::table('exam_questions')->orderBy('id')->each(function ($row): void {
            DB::table('exam_questions_geometry')->insert((array) $row);
        });
        Schema::drop('exam_questions');
        Schema::rename('exam_questions_geometry', 'exam_questions');
        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        // Existing geometry rows cannot be safely converted back to the old enum.
    }
};
