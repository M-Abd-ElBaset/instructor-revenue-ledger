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
        Schema::create('instructor_balances', function (Blueprint $table) {
            $table->foreignId('instructor_id')->primary()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('total_earned')->default(0);
            $table->unsignedBigInteger('total_paid')->default(0);
            $table->unsignedBigInteger('unpaid_balance')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instructor_balances');
    }
};
