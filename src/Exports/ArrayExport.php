<?php

declare(strict_types=1);

namespace Saroven\Reportify\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ArrayExport implements FromArray, WithHeadings
{
    private array $items;

    public function __construct(mixed $data = [])
    {
        if (is_object($data) && method_exists($data, 'toArray')) {
            $data = $data->toArray();
        }

        $this->items = (array) $data;
    }

    public function array(): array
    {
        return collect($this->items)
            ->map(function (mixed $row): array {
                if (is_object($row)) {
                    $row = method_exists($row, 'toArray') ? $row->toArray() : (array) $row;
                }
                if (!is_array($row)) {
                    return [(string) $row];
                }
                return collect($row)
                    ->reject(fn(mixed $value): bool => is_array($value) || is_object($value))
                    ->all();
            })
            ->all();
    }

    public function headings(): array
    {
        $first = reset($this->items);

        if (!$first) {
            return [];
        }

        if (is_object($first)) {
            $first = method_exists($first, 'toArray') ? $first->toArray() : (array) $first;
        }

        if (!is_array($first)) {
            return ['Value'];
        }

        return collect($first)
            ->reject(fn(mixed $value): bool => is_array($value) || is_object($value))
            ->keys()
            ->all();
    }
}
