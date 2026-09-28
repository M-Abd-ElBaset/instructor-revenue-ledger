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
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount');                  // integer cents
            $table->string('idempotency_key')->unique();           // generated once, reused on every retry
            $table->string('status')->default('pending');          // pending | processing | succeeded | failed | unknown
            $table->string('provider_reference')->nullable();
            $table->string('failure_reason')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['instructor_id', 'status']);
            $table->index(['status', 'updated_at']);               // the reconcile sweep scans this
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
