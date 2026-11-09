<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'password',
        'is_active',
        'must_change_password',
        'status',
        'role',
        'photo',
        'nik',
        'department_id',
        'position_id',
        'manager_id',
        'default_shift_id',
        'join_date',
    ];
    protected $casts = ['join_date' => 'date'];

    public function user()       { return $this->belongsTo(User::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function position()   { return $this->belongsTo(Position::class); }
    public function manager()    { return $this->belongsTo(Employee::class, 'manager_id'); }
    public function shift()      { return $this->belongsTo(Shift::class, 'default_shift_id'); }
    public function locations()  { return $this->belongsToMany(OfficeLocation::class, 'employee_locations'); }

    public function leaveRequests() { return $this->hasMany(LeaveRequest::class); }
    public function leaveBalances() { return $this->hasMany(LeaveBalance::class); }

    public function faceTemplates()
{
    return $this->hasMany(FaceTemplate::class);
}

public function approvedFaceTemplate()
{
    return $this->hasOne(FaceTemplate::class)->where('status', 'approved')->latest('id');
}

public function getFaceStatusAttribute(): string
{
    if ($this->approvedFaceTemplate) return 'Terdaftar';
    if ($this->faceTemplates()->where('status', 'pending')->exists()) return 'Menunggu persetujuan';
    return 'Belum terdaftar';
}

public function attendances()
{
    return $this->hasMany(\App\Models\Attendance::class, 'employee_id');
}
}