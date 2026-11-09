<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $guarded = [];
    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'is_half_day'  => 'boolean',
        'days'         => 'float',
        'cancelled_at' => 'datetime',
    ];

    public function employee()  { return $this->belongsTo(Employee::class); }
    public function type()      { return $this->belongsTo(LeaveType::class, 'leave_type_id'); }
    public function approvals() { return $this->morphMany(Approval::class, 'approvable')->orderBy('step'); }

    /** Langkah persetujuan yang sedang menunggu (urut berdasarkan step). */
    public function currentApproval(): ?Approval
    {
        return $this->status === 'pending'
            ? $this->approvals->where('status', 'pending')->sortBy('step')->first()
            : null;
    }

    public function canBeCancelled(): bool
    {
        return $this->status === 'pending'
            || ($this->status === 'approved' && $this->start_date->isFuture());
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'   => match ($this->currentApproval()?->role) {
                'atasan' => 'Menunggu atasan',
                'hrd'    => 'Menunggu HRD',
                default  => 'Menunggu',
            },
            'approved'  => 'Disetujui',
            'rejected'  => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default     => $this->status,
        };
    }

    /** Pengajuan yang langkah aktifnya dipegang role tertentu. */
    public function scopeAwaiting($query, string $role)
    {
        return $query->where('status', 'pending')->whereHas('approvals', function ($a) use ($role) {
            $a->where('role', $role)->where('status', 'pending')
              ->whereNotExists(function ($sub) {
                  $sub->selectRaw(1)->from('approvals as earlier')
                      ->whereColumn('earlier.approvable_id', 'approvals.approvable_id')
                      ->where('earlier.approvable_type', self::class)
                      ->whereColumn('earlier.step', '<', 'approvals.step')
                      ->where('earlier.status', 'pending');
              });
        });
    }
}