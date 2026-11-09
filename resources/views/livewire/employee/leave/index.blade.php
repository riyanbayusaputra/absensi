<div class="p-5">
    <a wire:navigate href="{{ route('app.home') }}" class="text-sm text-slate-500">&larr; Kembali</a>

    <div class="mt-2 flex items-center justify-between">
        <h1 class="text-xl font-bold">Cuti &amp; Izin</h1>
        <a wire:navigate href="{{ route('app.leave.create') }}"
           class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white active:bg-indigo-700">+ Ajukan</a>
    </div>

    @if (session('status'))
        <div class="mt-3 rounded-xl bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <p class="mt-5 text-xs font-semibold uppercase text-slate-400">Saldo {{ $year }}</p>
    <div class="mt-2 flex gap-3 overflow-x-auto pb-2">
        @forelse ($balances as $b)
            <div class="min-w-[9.5rem] rounded-2xl bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">{{ $b['name'] }}</p>
                <p class="mt-1 text-2xl font-bold text-indigo-600">
                    {{ $b['available'] + 0 }}<span class="text-xs font-normal text-slate-400"> hari</span>
                </p>
                <p class="text-[11px] text-slate-400">
                    Terpakai {{ $b['used'] + 0 }} dari {{ $b['total'] + 0 }}
                    @if ($b['pending'] > 0) · diajukan {{ $b['pending'] + 0 }} @endif
                </p>
            </div>
        @empty
            <p class="text-sm text-slate-400">Belum ada saldo cuti.</p>
        @endforelse
    </div>

    <p class="mt-4 text-xs font-semibold uppercase text-slate-400">Pengajuan</p>
    <div class="mt-2 space-y-3">
        @forelse ($requests as $r)
            @php($color = match ($r->status) {
                'approved'  => 'bg-green-100 text-green-700',
                'rejected'  => 'bg-red-100 text-red-700',
                'cancelled' => 'bg-slate-200 text-slate-600',
                default     => 'bg-amber-100 text-amber-700',
            })
            <div class="rounded-2xl bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold">{{ $r->type->name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $r->start_date->translatedFormat('d M Y') }}
                            @unless ($r->end_date->isSameDay($r->start_date)) &ndash; {{ $r->end_date->translatedFormat('d M Y') }} @endunless
                            · {{ $r->days + 0 }} hari
                            @if ($r->is_half_day) ({{ $r->half_day_session }}) @endif
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $color }}">{{ $r->status_label }}</span>
                </div>

                <p class="mt-2 text-sm text-slate-600">{{ $r->reason }}</p>

                <div class="mt-3 space-y-1">
                    @foreach ($r->approvals as $a)
                        <div class="text-xs">
                            <span class="{{ match ($a->status) { 'approved' => 'text-green-600', 'rejected' => 'text-red-600', default => 'text-slate-400' } }}">&#9679;</span>
                            {{ $a->role === 'atasan' ? 'Atasan' : 'HRD' }}:
                            {{ match ($a->status) { 'approved' => 'disetujui', 'rejected' => 'ditolak', 'skipped' => 'dilewati', default => 'menunggu' } }}
                            @if ($a->approver) <span class="text-slate-400">oleh {{ $a->approver->name }}</span> @endif
                            @if ($a->note) <span class="block pl-4 text-slate-500">&ldquo;{{ $a->note }}&rdquo;</span> @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 flex items-center justify-between text-xs">
                    @if ($r->attachment)
                        <a href="{{ route('leave.attachment', $r) }}" target="_blank" class="text-indigo-600">Lihat lampiran</a>
                    @else
                        <span></span>
                    @endif

                    @if ($r->canBeCancelled())
                        <button wire:click="cancel({{ $r->id }})" wire:confirm="Batalkan pengajuan ini?"
                                class="font-semibold text-red-600">Batalkan</button>
                    @endif
                </div>
            </div>
        @empty
            <p class="py-8 text-center text-sm text-slate-400">Belum ada pengajuan.</p>
        @endforelse
    </div>
</div>