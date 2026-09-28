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
        Schema::create('revenue_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->date('accrual_date');
            $table->unsignedBigInteger('amount');          // integer cents
            $table->timestamp('created_at')->useCurrent(); // append-only: no updated_at

            // the real idempotency guarantee for the nightly job
            $table->unique(['subscription_id', 'instructor_id', 'accrual_date'], 'ledger_accrual_unique');
            $table->index(['instructor_id', 'accrual_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_ledger_entries');
    }
};
