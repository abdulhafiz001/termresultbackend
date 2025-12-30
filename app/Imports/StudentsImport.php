<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentsImport implements ToArray, WithHeadingRow
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function array(array $array): array
    {
        // Excel::toArray() returns the parsed rows, so we don't need to do anything here.
        // This method exists to satisfy the ToArray contract.
        return $array;
    }
}


