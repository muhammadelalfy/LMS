<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('attendance_records', function (Blueprint $table) {
        $table->id(); $table->foreignId('student_id')->constrained()->cascadeOnDelete(); $table->date('date_at'); $table->enum('status', ['present','absent','late']); $table->text('note')->nullable(); $table->foreignId('recorded_by')->constrained('users'); $table->timestamps(); $table->unique(['student_id','date_at']);
    }); }
    public function down(): void { Schema::dropIfExists('attendance_records'); }
};
