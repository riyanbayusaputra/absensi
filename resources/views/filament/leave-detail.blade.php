<div style="font-size:14px;line-height:1.6">
    <p><b>{{ $r->employee->name }}</b> · {{ $r->employee->department?->name }}</p>
    <p>
        {{ $r->type->name }} · {{ $r->start_date->translatedFormat('d M Y') }}
        @unless ($r->end_date->isSameDay($r->start_date)) &ndash; {{ $r->end_date->translatedFormat('d M Y') }} @endunless
        · <b>{{ $r->days + 0 }} hari</b>
        @if ($r->is_half_day) ({{ $r->half_day_session }}) @endif
    </p>
    <p style="margin-top:6px"><b>Alasan:</b> {{ $r->reason }}</p>

    @if ($r->attachment)
        <p style="margin-top:6px">
            <a href="{{ route('leave.attachment', $r) }}" target="_blank" style="color:#6366f1">Buka lampiran</a>
        </p>
    @endif

    <p style="margin-top:10px;font-weight:600">Alur persetujuan</p>
    <ul style="margin:4px 0 12px 18px;list-style:disc">
        @foreach ($r->approvals as $a)
            <li>
                {{ $a->role === 'atasan' ? 'Atasan' : 'HRD' }}:
                {{ match ($a->status) { 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'skipped' => 'Dilewati', default => 'Menunggu' } }}
                @if ($a->approver) oleh {{ $a->approver->name }} ({{ $a->acted_at?->format('d M H:i') }}) @endif
                @if ($a->note) <span style="opacity:.65">&mdash; &ldquo;{{ $a->note }}&rdquo;</span> @endif
            </li>
        @endforeach
    </ul>
</div>