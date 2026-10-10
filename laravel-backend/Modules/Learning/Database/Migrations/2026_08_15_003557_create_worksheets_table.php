<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('worksheets', function (Blueprint $table) {
        $table->id(); $table->string('title'); $table->string('subject'); $table->string('grade'); $table->text('instructions')->nullable(); $table->dateTime('due_at')->nullable(); $table->enum('status', ['draft','published'])->default('draft'); $table->foreignId('created_by')->constrained('users'); $table->timestamps();
    }); }
    public function down(): void { Schema::dropIfExists('worksheets'); }
};
