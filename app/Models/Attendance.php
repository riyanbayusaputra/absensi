<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $guarded = [];
    protected $casts = [
        'date'         => 'date',
        'check_in_at'  => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function employee()    { return $this->belongsTo(Employee::class); }
    public function shift()       { return $this->belongsTo(Shift::class); }
    public function inLocation()  { return $this->belongsTo(OfficeLocation::class, 'in_location_id'); }
    public function outLocation() { return $this->belongsTo(OfficeLocation::class, 'out_location_id'); }
}