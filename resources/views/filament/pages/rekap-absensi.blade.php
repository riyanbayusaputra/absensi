<x-filament-panels::page>
    @php($colors = \App\Services\AttendanceReport::COLORS)
    @php($data = $this->recap)
    @php($border = 'border:1px solid rgba(128,128,128,.25)')

    {{-- Filter --}}
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
        <div style="min-width:150px">
            <label style="font-size:12px;opacity:.7">Bulan</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="month">
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div style="min-width:110px">
            <label style="font-size:12px;opacity:.7">Tahun</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="year">
                    @foreach (range(now()->year - 2, now()->year) as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div style="min-width:200px">
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
        @if ($tab === 'ringkasan')
            <div style="min-width:220px">
                <label style="font-size:12px;opacity:.7">Urutkan</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="sort">
                        <option value="nama">Nama</option>
                        <option value="telat">Terlambat terbanyak (menit)</option>
                        <option value="alfa">Alfa terbanyak</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @endif
    </div>

    <x-filament::tabs>
        <x-filament::tabs.item :active="$tab === 'matriks'" wire:click="$set('tab', 'matriks')">Matriks harian</x-filament::tabs.item>
        <x-filament::tabs.item :active="$tab === 'ringkasan'" wire:click="$set('tab', 'ringkasan')">Ringkasan per karyawan</x-filament::tabs.item>
        <x-filament::tabs.item :active="$tab === 'departemen'" wire:click="$set('tab', 'departemen')">Per departemen</x-filament::tabs.item>
    </x-filament::tabs>

    <x-filament::section>
        <p style="font-size:13px;opacity:.7;margin-bottom:8px">
            {{ $data['from']->translatedFormat('F Y') }} &middot; {{ count($data['rows']) }} karyawan
        </p>

        @if (empty($data['rows']))
            <p style="padding:24px;text-align:center;opacity:.6">Tidak ada data untuk filter ini.</p>

        @elseif ($tab === 'matriks')
            <div style="overflow-x:auto">
                <table style="border-collapse:collapse;font-size:12px;min-width:100%">
                    <thead>
                        <tr>
                            <th style="{{ $border }};padding:6px;text-align:left;min-width:170px">Karyawan</th>
                            @foreach ($data['dates'] as $d)
                                <th style="{{ $border }};padding:4px 2px;text-align:center;min-width:24px;{{ $d->isWeekend() ? 'opacity:.55' : '' }}">{{ $d->day }}</th>
                            @endforeach
                            @foreach (['H', 'T', 'A', 'C', 'S', 'I', 'K'] as $h)
                                <th style="{{ $border }};padding:4px;text-align:center">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['rows'] as $r)
                            @php($s = $r['sum'])
                            <tr>
                                <td style="{{ $border }};padding:4px 6px;white-space:nowrap">
                                    <b>{{ $r['employee']->name }}</b>
                                    <span style="opacity:.6;font-size:11px"> {{ $r['employee']->nik }}</span>
                                </td>
                                @foreach ($data['dates'] as $d)
                                    @php($code = $r['cells'][$d->toDateString()] ?? '')
                                    @php($c = $colors[$code] ?? null)
                                    <td style="{{ $border }};text-align:center;font-weight:600;{{ $c ? "background:{$c[0]};color:{$c[1]}" : '' }}">{{ $code }}</td>
                                @endforeach
                                <td style="{{ $border }};text-align:center">{{ $s['hadir'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $s['terlambat'] }}</td>
                                <td style="{{ $border }};text-align:center;{{ $s['alfa'] ? 'color:#dc2626;font-weight:700' : '' }}">{{ $s['alfa'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $s['cuti'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $s['sakit'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $s['izin'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $s['khusus'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px;font-size:12px">
                @foreach (\App\Services\AttendanceReport::LABELS as $code => $label)
                    <span style="padding:2px 8px;border-radius:6px;background:{{ $colors[$code][0] }};color:{{ $colors[$code][1] }}">{{ $code }} = {{ $label }}</span>
                @endforeach
            </div>

        @elseif ($tab === 'ringkasan')
            <div style="overflow-x:auto">
                <table style="border-collapse:collapse;font-size:13px;min-width:100%">
                    <thead>
                        <tr>
                            @foreach (['Karyawan', 'Departemen', 'Hari kerja', 'Hadir', 'Tepat waktu', 'Terlambat', 'Menit telat', 'Alfa', 'Cuti/Sakit/Izin', 'Jam kerja', '% Hadir'] as $h)
                                <th style="{{ $border }};padding:6px;text-align:{{ $loop->first ? 'left' : 'center' }}">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->sortedRows() as $r)
                            @php($s = $r['sum'])
                            <tr>
                                <td style="{{ $border }};padding:6px"><b>{{ $r['employee']->name }}</b><br><span style="opacity:.6;font-size:11px">{{ $r['employee']->nik }}</span></td>
                                <td style="{{ $border }};padding:6px">{{ $r['employee']->department?->name ?? '-' }}</td>
                                <td style="{{ $border }};text-align:center">{{ $s['hari_kerja'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $s['hadir'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $s['tepat'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $s['terlambat'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $s['late_min'] }}</td>
                                <td style="{{ $border }};text-align:center;{{ $s['alfa'] ? 'color:#dc2626;font-weight:700' : '' }}">{{ $s['alfa'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $s['cuti'] + $s['sakit'] + $s['izin'] + $s['khusus'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ intdiv($s['work_min'], 60) }}j {{ $s['work_min'] % 60 }}m</td>
                                <td style="{{ $border }};text-align:center">{{ $s['pct'] !== null ? $s['pct'] . '%' : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @else
            <div style="overflow-x:auto">
                <table style="border-collapse:collapse;font-size:13px;min-width:100%">
                    <thead>
                        <tr>
                            @foreach (['Departemen', 'Karyawan', 'Hadir', 'Terlambat', 'Menit telat', 'Alfa', 'Cuti/Sakit/Izin', '% Hadir'] as $h)
                                <th style="{{ $border }};padding:6px;text-align:{{ $loop->first ? 'left' : 'center' }}">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['departments'] as $x)
                            <tr>
                                <td style="{{ $border }};padding:6px"><b>{{ $x['name'] }}</b></td>
                                <td style="{{ $border }};text-align:center">{{ $x['employees'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $x['hadir'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $x['terlambat'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $x['late_min'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $x['alfa'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $x['leave'] }}</td>
                                <td style="{{ $border }};text-align:center">{{ $x['pct'] !== null ? $x['pct'] . '%' : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>