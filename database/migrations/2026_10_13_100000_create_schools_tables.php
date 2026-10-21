<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Central database: the list of schools and the addresses they answer on.
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            // The school's subdomain, e.g. "alnour".
            $table->string('id', 40)->primary();
            $table->string('name');
            $table->string('plan', 24)->default('trial');
            $table->string('status', 16)->default('active'); // active | suspended
            $table->timestamp('trial_ends_at')->nullable();
            $table->unsignedInteger('max_students')->nullable();
            $table->timestamps();
            // The school's own settings (payment, SMS, WhatsApp) and database name.
            $table->json('data')->nullable();
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain', 255)->unique();
            $table->string('tenant_id', 40);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
        Schema::dropIfExists('tenants');
    }
};
