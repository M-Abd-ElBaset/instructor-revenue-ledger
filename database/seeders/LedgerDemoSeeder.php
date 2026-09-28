<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\SubscriptionInstructorEnrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

class LedgerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = User::factory()->instructor()->count(3)->create();

        $subscription = Subscription::factory()->create([
            'term_starts_at' => now()->subDays(5)->startOfDay(),
            'term_ends_at' => now()->addDays(24)->startOfDay(), // 30-day term
        ]);

        foreach ($instructors as $instructor) {
            SubscriptionInstructorEnrollment::factory()->create([
                'subscription_id' => $subscription->id,
                'instructor_id' => $instructor->id,
                'enrolled_at' => $subscription->term_starts_at,
            ]);
        }
    }
}