<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mock_provider_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('outcome');                   // succeeded | failed: the provider's true result
            $table->string('response');                  // succeeded | failed | timeout: what the caller was told
            $table->string('provider_reference')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_provider_transactions');
    }
};
