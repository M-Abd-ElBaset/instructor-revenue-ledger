<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Ledger\AccrualCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccrueSubscriptionRevenue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $subscriptionId,
        public readonly string $accrualDate, // 'Y-m-d', so the job payload stays a plain string
    ) {}

    public function handle(AccrualCalculator $calculator): void
    {
        $subscription = Subscription::find($this->subscriptionId);

        if (! $subscription || $subscription->status !== SubscriptionStatus::Active) {
            return; // refunded/cancelled since dispatch, or deleted — nothing to accrue
        }

        $date = CarbonImmutable::parse($this->accrualDate);
        $start = CarbonImmutable::instance($subscription->term_starts_at);
        $end = CarbonImmutable::instance($subscription->term_ends_at);

        if ($date->lt($start) || $date->gt($end)) {
            return; // outside the term — shouldn't be dispatched, but stay safe
        }

        $instructorIds = $subscription->enrollments()
            ->where('enrolled_at', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('unenrolled_at')->orWhere('unenrolled_at', '>', $date);
            })
            ->pluck('instructor_id')
            ->all();

        if (empty($instructorIds)) {
            return; // no one enrolled today — platform keeps the day's revenue
        }

        $pool = $calculator->instructorPool($subscription->total_amount, $subscription->instructor_share_bps);
        $dailyAmount = $calculator->dailyAmount($pool, $start, $end, $date);
        $dayIndex = $calculator->dayIndex($start, $end, $date);
        $split = $calculator->split($dailyAmount, $instructorIds, $dayIndex);

        foreach ($split as $instructorId => $amount) {
            if ($amount <= 0) {
                continue;
            }

            DB::transaction(function () use ($subscription, $instructorId, $date, $amount) {
                $inserted = DB::table('revenue_ledger_entries')->insertOrIgnore([
                    'subscription_id' => $subscription->id,
                    'instructor_id' => $instructorId,
                    'accrual_date' => $date->toDateString(),
                    'amount' => $amount,
                    'created_at' => now(),
                ]);

                if ($inserted === 0) {
                    return; // already accrued for this subscription/instructor/day — safe no-op
                }

                DB::statement('
                    INSERT INTO instructor_balances (instructor_id, total_earned, total_paid, unpaid_balance, updated_at)
                    VALUES (?, ?, 0, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        total_earned = total_earned + VALUES(total_earned),
                        unpaid_balance = unpaid_balance + VALUES(unpaid_balance),
                        updated_at = VALUES(updated_at)
                ', [$instructorId, $amount, $amount, now()]);
            });
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Accrual failed', [
            'subscription_id' => $this->subscriptionId,
            'date' => $this->accrualDate,
            'error' => $exception->getMessage(),
        ]);
    }
}