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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users');
            $table->string('plan');                        // monthly | quarterly | annual
            $table->date('term_starts_at');
            $table->date('term_ends_at');                  // inclusive last day of the term
            $table->unsignedBigInteger('total_amount');    // integer cents
            $table->string('status')->default('active');   // active | refunded | cancelled
            $table->string('refund_reference')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            // the nightly job's main query filters on these
            $table->index(['status', 'term_starts_at', 'term_ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
