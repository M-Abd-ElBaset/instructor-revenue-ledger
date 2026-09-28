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
        Schema::table('revenue_ledger_entries', function (Blueprint $table) {
            $table->foreignId('payout_id')->nullable()->after('accrual_date')
                ->constrained('payouts')->restrictOnDelete();
            $table->index(['instructor_id', 'payout_id']);   // "unpaid entries for instructor X"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revenue_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['payout_id']);
            $table->dropIndex(['instructor_id', 'payout_id']);
            $table->dropColumn('payout_id');
        });
    }
};
