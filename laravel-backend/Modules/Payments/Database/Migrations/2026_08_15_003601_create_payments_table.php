<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('payments', function (Blueprint $table) {
        $table->id(); $table->foreignId('student_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('amount'); $table->enum('status', ['pending','paid','overdue'])->default('pending'); $table->date('due_at'); $table->date('paid_at')->nullable(); $table->text('note')->nullable(); $table->foreignId('recorded_by')->constrained('users'); $table->timestamps();
    }); }
    public function down(): void { Schema::dropIfExists('payments'); }
};
