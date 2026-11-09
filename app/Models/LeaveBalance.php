<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $guarded = [];
    protected $casts = ['quota' => 'float', 'carried_over' => 'float', 'used' => 'float'];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function type()     { return $this->belongsTo(LeaveType::class, 'leave_type_id'); }
    public function logs()     { return $this->hasMany(LeaveBalanceLog::class); }

    public function getRemainingAttribute(): float
    {
        return $this->quota + $this->carried_over - $this->used;
    }
}