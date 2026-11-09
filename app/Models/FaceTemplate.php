<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FaceTemplate extends Model
{
    protected $guarded = [];
    protected $hidden = ['descriptors'];
    protected $casts = [
        'descriptors'  => 'encrypted:array',
        'consented_at' => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function approve(User $by): void
    {
        DB::transaction(function () use ($by) {
            static::where('employee_id', $this->employee_id)
                ->where('status', 'approved')
                ->whereKeyNot($this->id)
                ->update(['status' => 'superseded']);

            $this->update([
                'status'        => 'approved',
                'reviewed_by'   => $by->id,
                'reviewed_at'   => now(),
                'reject_reason' => null,
            ]);
        });

        activity()->causedBy($by)->performedOn($this)->log('Registrasi wajah disetujui');
    }

    public function reject(User $by, string $reason): void
    {
        $this->update([
            'status'        => 'rejected',
            'reviewed_by'   => $by->id,
            'reviewed_at'   => now(),
            'reject_reason' => $reason,
        ]);

        activity()->causedBy($by)->performedOn($this)->log('Registrasi wajah ditolak: ' . $reason);
    }

    public function reset(User $by): void
    {
        $this->update(['status' => 'superseded', 'reviewed_by' => $by->id, 'reviewed_at' => now()]);

        activity()->causedBy($by)->performedOn($this)->log('Registrasi wajah direset');
    }
}