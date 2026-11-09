<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArraySheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        private string $title,
        private array $headings,
        private array $rows,
        private string $freeze = 'A2',
    ) {}

    public function array(): array { return $this->rows; }
    public function headings(): array { return $this->headings; }
    public function title(): string { return $this->title; }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane($this->freeze);

        return [1 => ['font' => ['bold' => true]]];
    }
}