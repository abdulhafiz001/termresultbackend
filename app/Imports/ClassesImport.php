<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClassesImport implements ToArray, WithHeadingRow
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function array(array $array): array
    {
        return $array;
    }
}


