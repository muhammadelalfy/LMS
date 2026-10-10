<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('exam_results', function (Blueprint $table) {
        $table->id(); $table->foreignId('student_id')->constrained()->cascadeOnDelete(); $table->string('title'); $table->unsignedInteger('score'); $table->unsignedInteger('max_score'); $table->date('taken_at'); $table->foreignId('recorded_by')->constrained('users'); $table->timestamps();
    }); }
    public function down(): void { Schema::dropIfExists('exam_results'); }
};
