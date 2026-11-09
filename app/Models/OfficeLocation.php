<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeLocation extends Model
{
    protected $fillable = ['name', 'address', 'latitude', 'longitude'];
    
    public function employees()
{
    return $this->belongsToMany(Employee::class, 'employee_locations');
}
}
