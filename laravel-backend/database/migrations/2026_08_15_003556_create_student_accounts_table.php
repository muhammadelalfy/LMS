<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('student_accounts', function (Blueprint $table) {
        $table->id(); $table->foreignId('student_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete(); $table->enum('relationship', ['student','parent']); $table->timestamps();
    }); }
    public function down(): void { Schema::dropIfExists('student_accounts'); }
};
