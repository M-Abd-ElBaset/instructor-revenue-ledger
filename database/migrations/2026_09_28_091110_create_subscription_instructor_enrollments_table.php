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
        Schema::create('subscription_instructor_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('users');
            $table->date('enrolled_at');                 // inclusive: earns from this day
            $table->date('unenrolled_at')->nullable();   // exclusive: stops earning on this day
            $table->timestamps();

            $table->index(['subscription_id', 'enrolled_at', 'unenrolled_at'], 'sie_subscription_window_idx');
            $table->index('instructor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_instructor_enrollments');
    }
};
