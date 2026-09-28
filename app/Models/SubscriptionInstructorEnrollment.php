<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionInstructorEnrollment extends Model
{
    protected $fillable = ['subscription_id', 'instructor_id', 'enrolled_at', 'unenrolled_at'];
    
    protected function casts(): array
    {
        return ['enrolled_at' => 'date', 'unenrolled_at' => 'date'];
    }
}
