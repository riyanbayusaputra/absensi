<div class="p-5">
    <a wire:navigate href="{{ route('app.leave') }}" class="text-sm text-slate-500">&larr; Kembali</a>
    <h1 class="mt-2 text-xl font-bold">Ajukan Cuti / Izin</h1>

    <form wire:submit="submit" class="mt-4 space-y-4">
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-500">Jenis</label>
            <select wire:model.live="leave_type_id" class="w-full rounded-xl border-slate-300 bg-white py-3 text-base">
                <option value="">Pilih jenis...</option>
                @foreach ($this->types as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
            @error('leave_type_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

            @if ($this->type)
                <p class="mt-1 text-[11px] text-slate-400">
                    @if ($this->type->min_notice_days) Diajukan minimal {{ $this->type->min_notice_days }} hari sebelumnya. @endif
                    @if ($this->type->backdate_days) Boleh diajukan sampai {{ $this->type->backdate_days }} hari ke belakang. @endif
                    @if ($this->type->requires_attachment) Lampiran wajib. @endif
                    @if ($this->type->max_days_per_request) Maks {{ $this->type->max_days_per_request }} hari. @endif
                </p>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500">Mulai</label>
                <input type="date" wire:model.live="start_date" class="w-full rounded-xl border-slate-300 bg-white py-3">
                @error('start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500">Selesai</label>
                <input type="date" wire:model.live="end_date" @disabled($is_half_day)
                       class="w-full rounded-xl border-slate-300 bg-white py-3 disabled:bg-slate-100">
                @error('end_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($this->type?->allow_half_day)
            <div class="rounded-xl bg-white p-3">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="is_half_day" class="rounded"> Setengah hari
                </label>
                @if ($is_half_day)
                    <select wire:model="half_day_session" class="mt-2 w-full rounded-lg border-slate-300 py-2 text-sm">
                        <option value="pagi">Pagi (tidak masuk pagi)</option>
                        <option value="siang">Siang (tidak masuk siang)</option>
                    </select>
                @endif
                @error('is_half_day') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        @if ($this->preview)
            <div class="rounded-xl bg-indigo-50 p-3 text-sm text-indigo-800">
                Durasi: <b>{{ $this->preview['days'] + 0 }} hari kerja</b>
                @if (! is_null($this->preview['available']))
                    · Saldo tersedia: <b>{{ $this->preview['available'] + 0 }} hari</b>
                @endif
                @if ($this->preview['days'] == 0)
                    <span class="block text-xs text-red-600">Rentang ini tidak berisi hari kerja.</span>
                @endif
            </div>
        @endif

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-500">Alasan</label>
            <textarea wire:model="reason" rows="3" class="w-full rounded-xl border-slate-300 bg-white"
                      placeholder="Jelaskan singkat alasan Anda"></textarea>
            @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-500">
                Lampiran {{ $this->type?->requires_attachment ? '(wajib)' : '(opsional)' }}
            </label>
            <input type="file" wire:model="attachment" accept="image/*,application/pdf"
                   class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm">
            <p class="mt-1 text-[11px] text-slate-400" wire:loading wire:target="attachment">Mengunggah...</p>
            @error('attachment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="submit,attachment"
                class="w-full rounded-xl bg-indigo-600 py-3 font-semibold text-white active:bg-indigo-700 disabled:opacity-50">
            Kirim Pengajuan
        </button>
    </form>
</div>