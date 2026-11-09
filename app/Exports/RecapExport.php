<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RecapExport implements Export, WithMultipleSheets
{
    public function __construct(
        private array $data
    ) {}

    public function sheets(): array
    {
        $d = $this->data;

        $mHead = ['NIK', 'Nama', 'Departemen'];

        foreach ($d['dates'] as $date) {
            $mHead[] = $date->day;
        }

        array_push($mHead,
            'Hadir', 'Terlambat', 'Alfa', 'Cuti',
            'Sakit', 'Izin', 'Cuti Khusus',
            'Menit Telat', '% Kehadiran'
        );

        $mRows = [];

        foreach ($d['rows'] as $r) {
            $s = $r['sum'];

            $row = [
                $r['employee']->nik,
                $r['employee']->name,
                $r['employee']->department?->name ?? '-',
            ];

            foreach ($d['dates'] as $date) {
                $row[] = $r['cells'][$date->toDateString()] ?? '';
            }

            $mRows[] = array_merge($row, [
                $s['hadir'], $s['terlambat'], $s['alfa'],
                $s['cuti'], $s['sakit'], $s['izin'],
                $s['khusus'], $s['late_min'], $s['pct'],
            ]);
        }

        $sHead = [
            'NIK', 'Nama', 'Departemen', 'Hari Kerja',
            'Hadir', 'Tepat Waktu', 'Terlambat',
            'Menit Telat', 'Menit Pulang Cepat',
            'Jam Kerja', 'Alfa', 'Cuti', 'Sakit',
            'Izin', 'Cuti Khusus', '% Kehadiran',
        ];

        $sRows = [];

        foreach ($d['rows'] as $r) {
            $s = $r['sum'];

            $sRows[] = [
                $r['employee']->nik,
                $r['employee']->name,
                $r['employee']->department?->name ?? '-',
                $s['hari_kerja'],
                $s['hadir'],
                $s['tepat'],
                $s['terlambat'],
                $s['late_min'],
                $s['early_min'],
                round($s['work_min'] / 60, 1),
                $s['alfa'],
                $s['cuti'],
                $s['sakit'],
                $s['izin'],
                $s['khusus'],
                $s['pct'],
            ];
        }

        $dHead = [
            'Departemen', 'Karyawan', 'Hadir',
            'Terlambat', 'Menit Telat', 'Alfa',
            'Cuti/Sakit/Izin', '% Kehadiran',
        ];

        $dRows = array_map(fn ($x) => [
            $x['name'],
            $x['employees'],
            $x['hadir'],
            $x['terlambat'],
            $x['late_min'],
            $x['alfa'],
            $x['leave'],
            $x['pct'],
        ], $d['departments']);

        return [
            new ArraySheet('Matriks', $mHead, $mRows, 'D2'),
            new ArraySheet('Ringkasan', $sHead, $sRows, 'D2'),
            new ArraySheet('Per Departemen', $dHead, $dRows),
        ];
    }
}