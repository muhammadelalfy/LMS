<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A daily duty (homework) for a whole group, due on a lesson day.
        // Groups are joined by name, like students.group.
        Schema::create('duties', function (Blueprint $table) {
            $table->id();
            $table->string('group', 32)->index();
            $table->string('title', 160);
            $table->text('details')->nullable();
            $table->date('due_on')->index();
            $table->timestamp('done_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duties');
    }
};
