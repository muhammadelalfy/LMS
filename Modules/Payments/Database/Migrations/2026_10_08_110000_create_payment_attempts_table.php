<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One try to pay a payment online through a provider (Fawry today:
        // a Pay at Fawry reference, or an e-wallet request such as Vodafone
        // Cash). A payment can have several attempts; the first one the
        // provider reports as paid settles it.
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('method', 24);
            $table->string('merchant_ref', 40)->unique();
            $table->string('reference_number', 64)->nullable();
            $table->string('wallet_mobile', 16)->nullable();
            $table->unsignedInteger('amount');
            $table->string('status', 16)->default('pending')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
