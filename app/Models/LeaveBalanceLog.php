<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalanceLog extends Model
{
    protected $guarded = [];
    protected $casts = ['change' => 'float'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}