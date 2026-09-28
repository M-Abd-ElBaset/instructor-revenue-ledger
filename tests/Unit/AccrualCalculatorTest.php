<?php

namespace Tests\Unit;

use App\Services\Ledger\AccrualCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

beforeEach(function () {
    $this->calc = new AccrualCalculator;
});

// ---------------------------------------------------------------------
// instructorPool
// ---------------------------------------------------------------------

it('computes the instructor pool at a given share', function () {
    expect($this->calc->instructorPool(12_000, 7_000))->toBe(8_400);
});

it('rounds the pool down when it does not divide evenly', function () {
    expect($this->calc->instructorPool(999, 7_000))->toBe(699); // 699.3 -> 699
});

it('gives the platform everything at 0 bps', function () {
    expect($this->calc->instructorPool(12_000, 0))->toBe(0);
});

it('gives instructors everything at 10000 bps', function () {
    expect($this->calc->instructorPool(12_000, 10_000))->toBe(12_000);
});

it('rejects a share above 10000 bps', function () {
    $this->calc->instructorPool(12_000, 10_001);
})->throws(InvalidArgumentException::class);

it('rejects a negative total amount', function () {
    $this->calc->instructorPool(-1, 7_000);
})->throws(InvalidArgumentException::class);

// ---------------------------------------------------------------------
// totalDays
// ---------------------------------------------------------------------

it('counts an inclusive range of days', function () {
    $start = CarbonImmutable::parse('2026-01-01');
    $end = CarbonImmutable::parse('2026-01-31');

    expect($this->calc->totalDays($start, $end))->toBe(31);
});

it('counts a same-day term as 1 day', function () {
    $day = CarbonImmutable::parse('2026-03-15');

    expect($this->calc->totalDays($day, $day))->toBe(1);
});

it('counts february correctly in a leap year', function () {
    $start = CarbonImmutable::parse('2028-02-01');
    $end = CarbonImmutable::parse('2028-02-29');

    expect($this->calc->totalDays($start, $end))->toBe(29);
});

it('rejects a term whose end is before its start', function () {
    $this->calc->totalDays(
        CarbonImmutable::parse('2026-01-10'),
        CarbonImmutable::parse('2026-01-01'),
    );
})->throws(InvalidArgumentException::class);

// ---------------------------------------------------------------------
// dayIndex / isLastDay
// ---------------------------------------------------------------------

it('gives day index 0 on the first day of the term', function () {
    $start = CarbonImmutable::parse('2026-01-01');
    $end = CarbonImmutable::parse('2026-01-31');

    expect($this->calc->dayIndex($start, $end, $start))->toBe(0);
});

it('gives the last day the index of days minus one', function () {
    $start = CarbonImmutable::parse('2026-01-01');
    $end = CarbonImmutable::parse('2026-01-31');

    expect($this->calc->dayIndex($start, $end, $end))->toBe(30)
        ->and($this->calc->isLastDay($start, $end, $end))->toBeTrue();
});

it('does not treat a middle day as the last day', function () {
    $start = CarbonImmutable::parse('2026-01-01');
    $end = CarbonImmutable::parse('2026-01-31');
    $middle = CarbonImmutable::parse('2026-01-15');

    expect($this->calc->isLastDay($start, $end, $middle))->toBeFalse();
});

it('rejects a date outside the term', function () {
    $start = CarbonImmutable::parse('2026-01-01');
    $end = CarbonImmutable::parse('2026-01-31');
    $outside = CarbonImmutable::parse('2026-02-01');

    $this->calc->dayIndex($start, $end, $outside);
})->throws(InvalidArgumentException::class);

// ---------------------------------------------------------------------
// dailyAmount
// ---------------------------------------------------------------------

it('splits a pool over three days with the last day absorbing the remainder', function () {
    $start = CarbonImmutable::parse('2026-01-01');
    $end = CarbonImmutable::parse('2026-01-03');
    $pool = 1_000; // 1000 / 3 = 333 r1

    expect($this->calc->dailyAmount($pool, $start, $end, CarbonImmutable::parse('2026-01-01')))->toBe(333)
        ->and($this->calc->dailyAmount($pool, $start, $end, CarbonImmutable::parse('2026-01-02')))->toBe(333)
        ->and($this->calc->dailyAmount($pool, $start, $end, CarbonImmutable::parse('2026-01-03')))->toBe(334);
});

it('sums daily amounts across the whole term back to the pool', function () {
    $start = CarbonImmutable::parse('2026-01-01');
    $end = CarbonImmutable::parse('2026-01-03');
    $pool = 1_000;

    $sum = 0;
    for ($date = $start; $date->lte($end); $date = $date->addDay()) {
        $sum += $this->calc->dailyAmount($pool, $start, $end, $date);
    }

    expect($sum)->toBe($pool);
});

it('rejects a date outside the term for dailyAmount', function () {
    $start = CarbonImmutable::parse('2026-01-01');
    $end = CarbonImmutable::parse('2026-01-03');

    $this->calc->dailyAmount(1_000, $start, $end, CarbonImmutable::parse('2026-02-01'));
})->throws(InvalidArgumentException::class);

// ---------------------------------------------------------------------
// split
// ---------------------------------------------------------------------

it('splits evenly when the amount divides cleanly', function () {
    expect($this->calc->split(90, [1, 2, 3], 0))->toBe([1 => 30, 2 => 30, 3 => 30]);
});

it('gives the remainder to instructors starting from the rotation offset', function () {
    // 100 / 3 = 33 r1 -> one instructor gets 34
    expect($this->calc->split(100, [1, 2, 3], 0))->toBe([1 => 34, 2 => 33, 3 => 33]);
});

it('rotates which instructor gets the remainder as the day index changes', function () {
    $day0 = $this->calc->split(100, [1, 2, 3], 0);
    $day1 = $this->calc->split(100, [1, 2, 3], 1);
    $day2 = $this->calc->split(100, [1, 2, 3], 2);

    expect($day0)->toBe([1 => 34, 2 => 33, 3 => 33])
        ->and($day1)->toBe([1 => 33, 2 => 34, 3 => 33])
        ->and($day2)->toBe([1 => 33, 2 => 33, 3 => 34]);
});

it('gives a single instructor the whole amount', function () {
    expect($this->calc->split(100, [7], 0))->toBe([7 => 100]);
});

it('returns an empty split when there are no instructors', function () {
    expect($this->calc->split(100, [], 0))->toBe([]);
});

it('ignores duplicate instructor ids', function () {
    expect($this->calc->split(90, [1, 1, 2, 3], 0))->toBe([1 => 30, 2 => 30, 3 => 30]);
});

it('is unaffected by the input order of instructor ids', function () {
    $sorted = $this->calc->split(100, [1, 2, 3], 1);
    $unsorted = $this->calc->split(100, [3, 1, 2], 1);

    expect($unsorted)->toBe($sorted);
});

it('gives one cent to some instructors and none to others when the amount is small', function () {
    $result = $this->calc->split(2, [1, 2, 3], 0);

    expect(array_sum($result))->toBe(2)
        ->and(array_filter($result, fn ($cents) => $cents === 1))->toHaveCount(2)
        ->and(array_filter($result, fn ($cents) => $cents === 0))->toHaveCount(1);
});

// ---------------------------------------------------------------------
// The core invariant: money is never created or lost across a full term
// ---------------------------------------------------------------------

it('always distributes the exact pool across every day of the term, for various term lengths and instructor counts', function (int $termDays, array $instructorIds) {
    $start = CarbonImmutable::parse('2026-01-01');
    $end = $start->addDays($termDays - 1);
    $pool = 10_037; // deliberately does not divide evenly by most instructor counts

    $total = 0;
    for ($date = $start; $date->lte($end); $date = $date->addDay()) {
        $dayIndex = $this->calc->dayIndex($start, $end, $date);
        $dailyAmount = $this->calc->dailyAmount($pool, $start, $end, $date);
        $split = $this->calc->split($dailyAmount, $instructorIds, $dayIndex);

        expect(array_sum($split))->toBe($dailyAmount);

        $total += array_sum($split);
    }

    expect($total)->toBe($pool);
})->with([
    'monthly, 1 instructor' => [30, [1]],
    'monthly, 2 instructors' => [30, [1, 2]],
    'quarterly, 3 instructors' => [90, [1, 2, 3]],
    'annual, 7 instructors' => [365, [1, 2, 3, 4, 5, 6, 7]],
]);