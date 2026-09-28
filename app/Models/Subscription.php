<?php

namespace App\Models;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['student_id', 'plan', 'term_starts_at', 'term_ends_at',
    'total_amount', 'status', 'refunded_at', 'refund_reference'];

    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'status' => SubscriptionStatus::class,
            'term_starts_at' => 'date',
            'term_ends_at' => 'date',
            'refunded_at' => 'datetime',
            'total_amount' => 'integer',
        ];
    }

    public function enrollments() { 
        return $this->hasMany(SubscriptionInstructorEnrollment::class); 
    }

    public function ledgerEntries() {
        return $this->hasMany(RevenueLedgerEntry::class); 
    }
}
