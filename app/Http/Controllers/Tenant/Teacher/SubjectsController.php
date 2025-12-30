<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubjectsController extends Controller
{
    public function index(Request $request)
    {
        $subjects = DB::table('subjects')
            ->select(['id', 'name', 'code', 'description'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $subjects]);
    }
}


