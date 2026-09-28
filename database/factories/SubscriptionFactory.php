<?php

namespace Database\Factories;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $plan = fake()->randomElement(SubscriptionPlan::cases());
        $start = CarbonImmutable::parse(fake()->dateTimeBetween('-2 months', 'now'))->startOfDay();
        $days = match ($plan) {
            SubscriptionPlan::Monthly => 30,
            SubscriptionPlan::Quarterly => 90,
            SubscriptionPlan::Annual => 365,
        };
        $end = $start->addDays($days - 1);

        $amountByPlan = [
            SubscriptionPlan::Monthly->value => 1_999,
            SubscriptionPlan::Quarterly->value => 4_999,
            SubscriptionPlan::Annual->value => 17_999,
        ];

        return [
            'student_id' => User::factory(),
            'plan' => $plan,
            'term_starts_at' => $start,
            'term_ends_at' => $end,
            'total_amount' => $amountByPlan[$plan->value],
            'instructor_share_bps' => config('ledger.instructor_share_bps'),
            'status' => SubscriptionStatus::Active,
        ];
    }

    public function refunded(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Refunded,
            'refunded_at' => now(),
        ]);
    }
}