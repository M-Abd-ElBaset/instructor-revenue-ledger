<?php

use App\Enums\SubscriptionStatus;
use App\Jobs\AccrueSubscriptionRevenue;
use App\Models\InstructorBalance;
use App\Models\RevenueLedgerEntry;
use App\Models\Subscription;
use App\Models\SubscriptionInstructorEnrollment;
use App\Models\User;
use App\Services\Ledger\AccrualCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // run dispatched jobs synchronously so a single Artisan::call
    // is enough to observe the end-to-end result in each test
    config(['queue.default' => 'sync']);
});

function enroll(Subscription $subscription, User $instructor, ?CarbonImmutable $enrolledAt = null, ?CarbonImmutable $unenrolledAt = null): SubscriptionInstructorEnrollment
{
    return SubscriptionInstructorEnrollment::factory()->create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'enrolled_at' => $enrolledAt ?? $subscription->term_starts_at,
        'unenrolled_at' => $unenrolledAt,
    ]);
}

it('creates one ledger entry per active instructor and credits their balance correctly', function () {
    $today = CarbonImmutable::today();

    $subscription = Subscription::factory()->create([
        'term_starts_at' => $today,
        'term_ends_at' => $today->addDays(4), // 5-day term, divides pool evenly
        'total_amount' => 10_000,
        'instructor_share_bps' => 5_000, // pool = 5000
    ]);

    $instructors = User::factory()->instructor()->count(3)->create();
    foreach ($instructors as $instructor) {
        enroll($subscription, $instructor);
    }

    Artisan::call('revenue:accrue', ['--date' => $today->toDateString()]);

    expect(RevenueLedgerEntry::where('subscription_id', $subscription->id)->count())->toBe(3);

    // pool 5000 / 5 days = 1000 exactly, so today's total is 1000
    expect(RevenueLedgerEntry::where('subscription_id', $subscription->id)->sum('amount'))->toBe(1000);

    foreach ($instructors as $instructor) {
        $balance = InstructorBalance::find($instructor->id);
        $ledgerAmount = RevenueLedgerEntry::where('instructor_id', $instructor->id)->sum('amount');

        expect($balance)->not->toBeNull()
            ->and($balance->total_earned)->toBe($ledgerAmount)
            ->and($balance->unpaid_balance)->toBe($ledgerAmount)
            ->and($balance->total_paid)->toBe(0);
    }
});

it('does not double-accrue when the command runs twice for the same date', function () {
    $today = CarbonImmutable::today();

    $subscription = Subscription::factory()->create([
        'term_starts_at' => $today,
        'term_ends_at' => $today->addDays(4),
        'total_amount' => 10_000,
        'instructor_share_bps' => 5_000,
    ]);

    $instructors = User::factory()->instructor()->count(3)->create();
    foreach ($instructors as $instructor) {
        enroll($subscription, $instructor);
    }

    Artisan::call('revenue:accrue', ['--date' => $today->toDateString()]);

    $countAfterFirst = RevenueLedgerEntry::count();
    $balancesAfterFirst = InstructorBalance::orderBy('instructor_id')->pluck('unpaid_balance', 'instructor_id')->all();

    Artisan::call('revenue:accrue', ['--date' => $today->toDateString()]);

    expect(RevenueLedgerEntry::count())->toBe($countAfterFirst)
        ->and(InstructorBalance::orderBy('instructor_id')->pluck('unpaid_balance', 'instructor_id')->all())
        ->toBe($balancesAfterFirst);
});

it('does not double-credit when the job itself is re-run, simulating a retried job', function () {
    $today = CarbonImmutable::today();

    $subscription = Subscription::factory()->create([
        'term_starts_at' => $today,
        'term_ends_at' => $today->addDays(2),
        'total_amount' => 3_000,
        'instructor_share_bps' => 5_000,
    ]);

    $instructor = User::factory()->instructor()->create();
    enroll($subscription, $instructor);

    $calculator = app(AccrualCalculator::class);
    $date = $today->toDateString();

    (new AccrueSubscriptionRevenue($subscription->id, $date))->handle($calculator);
    $countAfterFirst = RevenueLedgerEntry::count();
    $earnedAfterFirst = InstructorBalance::find($instructor->id)->total_earned;

    // simulate the queue retrying the exact same job payload
    (new AccrueSubscriptionRevenue($subscription->id, $date))->handle($calculator);

    expect(RevenueLedgerEntry::count())->toBe($countAfterFirst)
        ->and(InstructorBalance::find($instructor->id)->total_earned)->toBe($earnedAfterFirst);
});

it('does not accrue for a refunded subscription', function () {
    $today = CarbonImmutable::today();

    $subscription = Subscription::factory()->refunded()->create([
        'term_starts_at' => $today->subDays(2),
        'term_ends_at' => $today->addDays(2),
    ]);

    $instructor = User::factory()->instructor()->create();
    enroll($subscription, $instructor, $subscription->term_starts_at);

    Artisan::call('revenue:accrue', ['--date' => $today->toDateString()]);

    expect(RevenueLedgerEntry::where('subscription_id', $subscription->id)->exists())->toBeFalse();
});

it('accrues nothing for a subscription with no enrolled instructors, without erroring', function () {
    $today = CarbonImmutable::today();

    $subscription = Subscription::factory()->create([
        'term_starts_at' => $today,
        'term_ends_at' => $today->addDays(4),
    ]);

    Artisan::call('revenue:accrue', ['--date' => $today->toDateString()]);

    expect(RevenueLedgerEntry::where('subscription_id', $subscription->id)->exists())->toBeFalse();
});

it('skips accrual when the job is given a date outside the subscription term', function () {
    $today = CarbonImmutable::today();

    $subscription = Subscription::factory()->create([
        'term_starts_at' => $today,
        'term_ends_at' => $today->addDays(2),
    ]);

    $instructor = User::factory()->instructor()->create();
    enroll($subscription, $instructor);

    $outOfRangeDate = $today->addDays(10)->toDateString();

    (new AccrueSubscriptionRevenue($subscription->id, $outOfRangeDate))
        ->handle(app(AccrualCalculator::class));

    expect(RevenueLedgerEntry::count())->toBe(0);
});

it('excludes an instructor whose enrollment window ended before the accrual date', function () {
    $today = CarbonImmutable::today();

    $subscription = Subscription::factory()->create([
        'term_starts_at' => $today->subDays(2),
        'term_ends_at' => $today->addDays(2),
    ]);

    $activeInstructor = User::factory()->instructor()->create();
    $droppedInstructor = User::factory()->instructor()->create();

    enroll($subscription, $activeInstructor, $subscription->term_starts_at);
    enroll($subscription, $droppedInstructor, $subscription->term_starts_at, $today); // window ends today, exclusive

    Artisan::call('revenue:accrue', ['--date' => $today->toDateString()]);

    expect(RevenueLedgerEntry::where('instructor_id', $activeInstructor->id)->exists())->toBeTrue()
        ->and(RevenueLedgerEntry::where('instructor_id', $droppedInstructor->id)->exists())->toBeFalse();
});