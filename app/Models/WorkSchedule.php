<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    protected $guarded = [];
    protected $casts = ['date' => 'date', 'is_off' => 'boolean'];

    public function employee() { return $this->belongsTo(Employee::class); }
}