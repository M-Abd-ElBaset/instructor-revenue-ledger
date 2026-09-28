<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $fillable = ['instructor_id', 'amount', 'idempotency_key', 'status',
    'provider_reference', 'failure_reason', 'attempts', 'initiated_at', 'resolved_at'];
    
    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount' => 'integer',
            'initiated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function ledgerEntries() {
        return $this->hasMany(RevenueLedgerEntry::class); 
    }
    
}
