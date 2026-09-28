<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionInstructorEnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'instructor_id' => User::factory()->instructor(),
            'enrolled_at' => now()->startOfDay(),
            'unenrolled_at' => null,
        ];
    }

    public function endedAt(\DateTimeInterface|string $date): static
    {
        return $this->state(fn () => ['unenrolled_at' => $date]);
    }
}