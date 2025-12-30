<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementsController extends Controller
{
    public function index(Request $request)
    {
        $teacherId = $request->user()->id;
        
        $items = DB::table('announcements')
            ->where('for_teachers', true)
            ->orderByDesc('published_at')
            ->limit(200)
            ->get()
            ->map(function ($a) use ($teacherId) {
                $isRead = DB::table('announcement_views')
                    ->where('announcement_id', $a->id)
                    ->where('user_id', $teacherId)
                    ->exists();
                
                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'published_at' => $a->published_at,
                    'is_read' => $isRead,
                ];
            });

        return response()->json(['data' => $items]);
    }

    public function unreadCount(Request $request)
    {
        $teacherId = $request->user()->id;
        
        $total = DB::table('announcements')
            ->where('for_teachers', true)
            ->whereNotIn('id', function ($query) use ($teacherId) {
                $query->select('announcement_id')
                    ->from('announcement_views')
                    ->where('user_id', $teacherId);
            })
            ->count();

        return response()->json(['count' => $total]);
    }

    public function markAsRead(Request $request, int $id)
    {
        $teacherId = $request->user()->id;
        
        DB::table('announcement_views')->insertOrIgnore([
            'announcement_id' => $id,
            'user_id' => $teacherId,
            'viewed_at' => now(),
        ]);

        return response()->json(['message' => 'Announcement marked as read.']);
    }
}


