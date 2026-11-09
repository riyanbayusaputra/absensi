<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $guarded = [];
    protected $casts = [
        'deducts_balance'     => 'boolean',
        'requires_attachment' => 'boolean',
        'allow_half_day'      => 'boolean',
        'is_active'           => 'boolean',
        'annual_quota'        => 'float',
        'carry_over_max'      => 'float',
    ];
}