<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementsController extends Controller
{
    public function index()
    {
        $items = DB::table('announcements')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'for_teachers' => (bool) $a->for_teachers,
                    'for_all_students' => (bool) $a->for_all_students,
                    'class_ids' => $a->class_ids ? json_decode($a->class_ids, true) : [],
                    'published_at' => $a->published_at,
                    'created_at' => $a->created_at,
                ];
            });

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'for_teachers' => ['boolean'],
            'for_all_students' => ['boolean'],
            'class_ids' => ['array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
        ]);

        $id = DB::table('announcements')->insertGetId([
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'for_teachers' => (bool) ($data['for_teachers'] ?? false),
            'for_all_students' => (bool) ($data['for_all_students'] ?? false),
            'class_ids' => json_encode($data['class_ids'] ?? []),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:10000'],
            'for_teachers' => ['boolean'],
            'for_all_students' => ['boolean'],
            'class_ids' => ['array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
        ]);

        $update = [];
        foreach (['title', 'body', 'for_teachers', 'for_all_students'] as $k) {
            if (array_key_exists($k, $data)) $update[$k] = $data[$k];
        }
        if (array_key_exists('class_ids', $data)) $update['class_ids'] = json_encode($data['class_ids']);
        $update['updated_at'] = now();

        DB::table('announcements')->where('id', $id)->update($update);

        return response()->json(['message' => 'Announcement updated.']);
    }

    public function destroy(int $id)
    {
        DB::table('announcements')->where('id', $id)->delete();
        return response()->json(['message' => 'Announcement deleted.']);
    }
}


