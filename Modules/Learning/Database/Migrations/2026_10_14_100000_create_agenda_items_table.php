<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A teacher's own to-do for one group on one day (prepare a quiz,
        // follow up a student). Never shown to students or parents.
        // Groups are joined by name, like students.group and duties.group.
        Schema::create('agenda_items', function (Blueprint $table) {
            $table->id();
            $table->string('group', 32)->index();
            $table->string('title', 160);
            $table->text('details')->nullable();
            $table->date('due_on')->index();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_items');
    }
};
