<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_products', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version', 32)->default('1.0.0');
            $table->string('module_name', 120)->unique();
            $table->string('artifact_path')->nullable();
            $table->string('artifact_sha256', 64)->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('plugin_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plugin_product_id')->constrained('plugin_products')->cascadeOnDelete();
            $table->string('status', 32)->default('completed');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'plugin_product_id']);
        });

        Schema::create('installed_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_product_id')->constrained('plugin_products')->cascadeOnDelete();
            $table->foreignId('installed_by')->constrained('users')->restrictOnDelete();
            $table->string('module_name', 120)->unique();
            $table->string('version', 32);
            $table->string('path');
            $table->string('status', 32)->default('installed');
            $table->json('config')->nullable();
            $table->timestamp('installed_at');
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installed_modules');
        Schema::dropIfExists('plugin_purchases');
        Schema::dropIfExists('plugin_products');
    }
};
