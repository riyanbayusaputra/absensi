<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Storage;

class LeaveAttachmentController extends Controller
{
    public function show(LeaveRequest $leaveRequest)
    {
        $u = auth()->user();
        $leaveRequest->loadMissing('employee');

        $allowed = $u->hasAnyRole(['super_admin', 'hrd'])
            || $u->employee?->id === $leaveRequest->employee_id
            || ($u->employee && $u->employee->id === $leaveRequest->employee->manager_id);

        abort_unless($allowed, 403);
        abort_unless($leaveRequest->attachment && Storage::disk('local')->exists($leaveRequest->attachment), 404);

        return Storage::disk('local')->response($leaveRequest->attachment);
    }
}