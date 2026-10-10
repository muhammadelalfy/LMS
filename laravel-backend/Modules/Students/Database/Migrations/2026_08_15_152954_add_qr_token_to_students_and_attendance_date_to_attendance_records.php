<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('qr_token', 96)->nullable()->unique()->after('status');
        });

        DB::table('students')->select('id')->orderBy('id')->each(function (object $student): void {
            DB::table('students')->where('id', $student->id)->update(['qr_token' => Str::random(64)]);
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->date('attendance_date')->nullable()->after('date_at')->index();
        });

        DB::statement('UPDATE attendance_records SET attendance_date = DATE(date_at) WHERE attendance_date IS NULL');
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropColumn('attendance_date');
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique(['qr_token']);
            $table->dropColumn('qr_token');
        });
    }
};
