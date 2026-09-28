<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueLedgerEntry extends Model
{
    const UPDATED_AT = null; //append-only, so no updated_at

    protected $fillable = ['subscription_id', 'instructor_id', 'accrual_date', 'amount', 'payout_id'];

    protected function casts(): array
    {
        return [
            'accrual_date' => 'date', 
            'amount' => 'integer'
        ];
    }
}
