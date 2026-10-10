<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('worksheet_assignments', function (Blueprint $table) {
        $table->id(); $table->foreignId('worksheet_id')->constrained()->cascadeOnDelete(); $table->foreignId('student_id')->constrained()->cascadeOnDelete(); $table->enum('status', ['assigned','in_progress','submitted','graded'])->default('assigned'); $table->dateTime('assigned_at'); $table->dateTime('submitted_at')->nullable(); $table->unsignedInteger('score')->nullable(); $table->unsignedInteger('max_score')->nullable(); $table->text('feedback')->nullable(); $table->timestamps(); $table->unique(['worksheet_id','student_id']);
    }); }
    public function down(): void { Schema::dropIfExists('worksheet_assignments'); }
};
