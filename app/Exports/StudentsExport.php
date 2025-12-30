<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StudentsExport implements FromCollection, WithHeadings
{
    public function __construct(private ?int $classId = null) {}

    public function headings(): array
    {
        return [
            'Admission Number',
            'Last Name',
            'First Name',
            'Middle Name',
            'Class',
            'Gender',
            'DOB',
            'Email',
            'Phone',
            'Status',
        ];
    }

    public function collection(): Collection
    {
        $q = DB::table('users')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->leftJoin('classes', 'classes.id', '=', 'student_profiles.current_class_id')
            ->where('users.role', 'student')
            ->select([
                'users.admission_number',
                'student_profiles.last_name',
                'student_profiles.first_name',
                'student_profiles.middle_name',
                'classes.name as class_name',
                'student_profiles.gender',
                'student_profiles.date_of_birth',
                'student_profiles.email',
                'student_profiles.phone',
                'users.status',
            ])
            ->orderBy('student_profiles.last_name');

        if ($this->classId) {
            $q->where('student_profiles.current_class_id', $this->classId);
        }

        return $q->get()->map(function ($r) {
            return [
                $r->admission_number,
                $r->last_name,
                $r->first_name,
                $r->middle_name,
                $r->class_name,
                $r->gender,
                $r->date_of_birth,
                $r->email,
                $r->phone,
                $r->status,
            ];
        });
    }
}


