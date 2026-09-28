<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Jobs\AccrueSubscriptionRevenue;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AccrueRevenue extends Command
{
    protected $signature = 'revenue:accrue {--date=}';
    protected $description = 'Dispatch one accrual job per active subscription for the given date (defaults to today).';

    public function handle(): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))
            : CarbonImmutable::today();

        $count = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->where('term_starts_at', '<=', $date)
            ->where('term_ends_at', '>=', $date)
            ->select('id')
            ->chunkById(500, function ($subscriptions) use ($date, &$count) {
                foreach ($subscriptions as $subscription) {
                    AccrueSubscriptionRevenue::dispatch($subscription->id, $date->toDateString());
                    $count++;
                }
            });

        $this->info("Dispatched accrual for {$count} subscription(s) on {$date->toDateString()}.");

        return self::SUCCESS;
    }
}