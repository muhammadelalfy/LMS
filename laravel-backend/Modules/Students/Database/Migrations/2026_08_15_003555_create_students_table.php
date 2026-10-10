<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('students', function (Blueprint $table) {
        $table->id(); $table->string('name'); $table->string('group'); $table->string('grade'); $table->string('phone'); $table->string('parent_phone')->nullable(); $table->enum('status', ['excellent','average','weak'])->default('average'); $table->timestamps();
    }); }
    public function down(): void { Schema::dropIfExists('students'); }
};
