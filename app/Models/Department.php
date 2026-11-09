<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
 protected $fillable = ['name', 'description'];


    // app/Models/Department.php dan app/Models/Position.php
public function employees()
{
    return $this->hasMany(Employee::class);
}
}
