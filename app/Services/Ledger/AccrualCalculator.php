<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use Carbon\CarbonInterface;
use InvalidArgumentException;

final class AccrualCalculator
{
    /** Instructor pool in cents. Rounds down, so fractions of a cent stay with the platform. */
    public function instructorPool(int $totalAmount, int $shareBps): int
    {
        if ($totalAmount < 0 || $shareBps < 0 || $shareBps > 10_000) {
            throw new InvalidArgumentException('Invalid amount or share.');
        }

        return intdiv($totalAmount * $shareBps, 10_000);
    }

    /** Inclusive number of days in the term. */
    public function totalDays(CarbonInterface $start, CarbonInterface $end): int
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidArgumentException('Term end is before term start.');
        }

        $days = (int) $start->diffInDays($end) + 1;

        return $days;
    }

    /** 0-based day index of $date within the term. */
    public function dayIndex(CarbonInterface $start, CarbonInterface $end, CarbonInterface $date): int
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();
        $date = $date->copy()->startOfDay();

        if ($date->lt($start) || $date->gt($end)) {
            throw new InvalidArgumentException('Date is outside the subscription term.');
        }

        return (int) $start->diffInDays($date);
    }

    public function isLastDay(CarbonInterface $start, CarbonInterface $end, CarbonInterface $date): bool
    {
        return $this->dayIndex($start, $end, $date) === $this->totalDays($start, $end) - 1;
    }

    /** Amount to distribute on one day. The last day absorbs the leftover cents. */
    public function dailyAmount(int $pool, CarbonInterface $start, CarbonInterface $end, CarbonInterface $date): int
    {
        $days = $this->totalDays($start, $end);
        $base = intdiv($pool, $days);

        if($this->isLastDay($start, $end, $date))
        {
            return $base + ($pool % $days);    
        }

        return $base;
    }

    /**
     * Equal split across instructors; leftover cents rotate by day.
     *
     * @param  int[]  $instructorIds
     * @return array<int, int>  instructorId => cents (always sums to $dailyAmount)
     */
    public function split(int $dailyAmount, array $instructorIds, int $dayIndex): array
    {
        $ids = array_values(array_unique($instructorIds));
        sort($ids);

        $count = count($ids);

        if ($count === 0) {
            return [];
        }

        $share = intdiv($dailyAmount, $count);
        $remainder = $dailyAmount % $count;
        $offset = $dayIndex % $count;

        $result = [];
        foreach ($ids as $position => $id) {
            $rotated = ($position - $offset + $count) % $count;
            $result[$id] = $share + ($rotated < $remainder ? 1 : 0);
        }

        return $result;
    }
}