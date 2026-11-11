<x-filament-panels::page>
    @php
        $employees = $this->employees();
        $days      = $this->days();
        $holidays  = $this->holidays();
        $canEdit   = $this->canEditSchedule();
        $names     = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
        $border    = 'border:1px solid rgba(128,128,128,.25)';
    @endphp

    {{-- Navigasi minggu + filter --}}
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <x-filament::button color="gray" size="sm" icon="heroicon-o-chevron-left" wire:click="prevWeek">Minggu lalu</x-filament::button>

        <div style="min-width:160px">
            <label style="font-size:12px;opacity:.7">Pilih tanggal (minggu otomatis)</label>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="week" />
            </x-filament::input.wrapper>
        </div>

        <x-filament::button color="gray" size="sm" icon-position="after" icon="heroicon-o-chevron-right" wire:click="nextWeek">Minggu depan</x-filament::button>
        <x-filament::button color="gray" size="sm" wire:click="thisWeek">Minggu ini</x-filament::button>

        <div style="min-width:200px;margin-left:auto">
            <label style="font-size:12px;opacity:.7">Departemen</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="departmentId">
                    <option value="">Semua departemen</option>
                    @foreach ($this->departments() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </div>

    <x-filament::section>
        <p style="font-size:14px;font-weight:600">
            {{ $days[0]->translatedFormat('d M') }} &ndash; {{ $days[6]->translatedFormat('d M Y') }}
            <span style="font-weight:400;opacity:.65"> &middot; {{ $employees->count() }} karyawan</span>
        </p>
        <p style="font-size:12px;opacity:.7;margin:4px 0 10px">
            @if ($canEdit)
                Centang <b>Libur</b> pada hari libur masing-masing karyawan. Perubahan baru tersimpan setelah klik <b>Simpan jadwal</b>;
                pindah minggu atau filter sebelum menyimpan akan membuang perubahan. Minggu yang belum diatur memakai pola standar.
            @else
                Tampilan jadwal tim Anda (hanya lihat).
            @endif
        </p>

        @if ($employees->isEmpty())
            <p style="padding:24px;text-align:center;opacity:.6">Tidak ada karyawan aktif untuk filter ini.</p>
        @else
            <div style="overflow-x:auto">
                <table style="border-collapse:collapse;font-size:13px;min-width:100%">
                    <thead>
                        <tr>
                            <th style="{{ $border }};padding:6px;text-align:left;min-width:190px">Karyawan</th>
                            @foreach ($days as $i => $d)
                                @php($ds = $d->toDateString())
                                <th style="{{ $border }};padding:6px;text-align:center;min-width:92px;{{ isset($holidays[$ds]) ? 'background:rgba(59,130,246,.15)' : '' }}">
                                    {{ $names[$i] }}<br>
                                    <span style="font-weight:400;opacity:.7">{{ $d->format('d/m') }}</span>
                                    @if (isset($holidays[$ds]))
                                        <br><span style="font-size:10px;font-weight:400">{{ \Illuminate\Support\Str::limit($holidays[$ds], 14) }}</span>
                                    @endif
                                </th>
                            @endforeach
                            <th style="{{ $border }};padding:6px;text-align:center">Hari masuk</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $e)
                            @php($work = 0)
                            <tr>
                                <td style="{{ $border }};padding:6px">
                                    <b>{{ $e->name }}</b>
                                    <span style="opacity:.6;font-size:11px"> {{ $e->nik }}</span><br>
                                    <span style="font-size:11px;opacity:.65">{{ $e->department?->name ?? '-' }}</span>
                                    @unless ($this->configured[$e->id] ?? false)
                                        <span style="font-size:10px;background:#fef3c7;color:#92400e;border-radius:6px;padding:1px 6px;margin-left:4px">belum diatur</span>
                                    @endunless
                                </td>

                                @foreach ($days as $d)
                                    @php($ds = $d->toDateString())
                                    @php($isHoliday = isset($holidays[$ds]))
                                    @php($off = (bool) ($this->grid[$e->id][$ds] ?? false))
                                    @php($work += ($isHoliday || $off) ? 0 : 1)

                                    <td style="{{ $border }};text-align:center;padding:4px;
                                        {{ $isHoliday ? 'background:#dbeafe' : ($off ? 'background:#e5e7eb' : 'background:#dcfce7') }}">
                                        @if ($isHoliday)
                                            <span style="font-size:11px;color:#1e40af">Libur nasional</span>
                                        @elseif ($canEdit)
                                            <label style="display:flex;align-items:center;justify-content:center;gap:6px;cursor:pointer;font-size:12px;font-weight:600;color:{{ $off ? '#4b5563' : '#166534' }}">
                                                <input type="checkbox" wire:model.live="grid.{{ $e->id }}.{{ $ds }}" style="width:16px;height:16px">
                                                {{ $off ? 'Libur' : 'Masuk' }}
                                            </label>
                                        @else
                                            <span style="font-size:12px;font-weight:600;color:{{ $off ? '#4b5563' : '#166534' }}">{{ $off ? 'Libur' : 'Masuk' }}</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td style="{{ $border }};text-align:center;font-weight:700">{{ $work }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td style="{{ $border }};padding:6px;font-weight:600">Jumlah yang masuk</td>
                            @foreach ($days as $d)
                                @php($ds = $d->toDateString())
                                @php($count = isset($holidays[$ds]) ? 0 : $employees->filter(fn ($e) => ! ($this->grid[$e->id][$ds] ?? false))->count())
                                <td style="{{ $border }};text-align:center;font-weight:700">{{ $count }}</td>
                            @endforeach
                            <td style="{{ $border }}"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>