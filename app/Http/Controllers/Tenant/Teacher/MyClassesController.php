<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MyClassesController extends Controller
{
    public function index(Request $request)
    {
        $teacherId = $request->user()->id;

        $classes = DB::table('teacher_class')
            ->join('classes', 'classes.id', '=', 'teacher_class.class_id')
            ->where('teacher_class.teacher_id', $teacherId)
            ->select(['classes.id', 'classes.name'])
            ->orderBy('classes.name')
            ->get();

        $subjects = DB::table('teacher_subject')
            ->join('subjects', 'subjects.id', '=', 'teacher_subject.subject_id')
            ->where('teacher_subject.teacher_id', $teacherId)
            ->select(['subjects.id', 'subjects.name', 'subjects.code'])
            ->orderBy('subjects.name')
            ->get();

        return response()->json([
            'classes' => $classes,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Classes where the teacher is the form teacher.
     */
    public function formClasses(Request $request)
    {
        $teacherId = $request->user()->id;

        $classes = DB::table('classes')
            ->where('form_teacher_id', $teacherId)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'classes' => $classes,
        ]);
    }
}


