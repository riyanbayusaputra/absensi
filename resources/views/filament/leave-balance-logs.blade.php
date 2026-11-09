<div style="font-size:14px">
    @forelse ($logs as $l)
        <div style="display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid rgba(128,128,128,.25)">
            <div>
                <div>{{ $l->reason }}</div>
                <div style="font-size:12px;opacity:.65">
                    {{ $l->created_at->format('d M Y H:i') }}@if ($l->creator) · {{ $l->creator->name }}@endif
                </div>
            </div>
            <b style="color:{{ $l->change < 0 ? '#dc2626' : '#16a34a' }}">{{ $l->change > 0 ? '+' : '' }}{{ $l->change + 0 }}</b>
        </div>
    @empty
        <p>Belum ada mutasi.</p>
    @endforelse
</div>