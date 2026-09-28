<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorBalance extends Model
{
    const CREATED_AT = null;

    protected $primaryKey = 'instructor_id'; //the primary key is instructor_id, not an auto-increment id

    public $incrementing = false;
    
    protected $fillable = ['instructor_id', 'total_earned', 'total_paid', 'unpaid_balance'];
    
}
