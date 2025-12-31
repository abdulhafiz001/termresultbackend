<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementsController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;
        
        $items = TenantDB::table('announcements')
            ->where('for_teachers', true)
            ->orderByDesc('published_at')
            ->limit(200)
            ->get()
            ->map(function ($a) use ($teacherId) {
                $isRead = TenantDB::table('announcement_views')
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
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;
        
        $total = DB::table('announcements')
            ->where('tenant_id', $tenantId)
            ->where('for_teachers', true)
            ->whereNotIn('id', function ($query) use ($teacherId, $tenantId) {
                $query->select('announcement_id')
                    ->from('announcement_views')
                    ->where('user_id', $teacherId)
                    ->where('tenant_id', $tenantId);
            })
            ->count();

        return response()->json(['count' => $total]);
    }

    public function markAsRead(Request $request, int $id)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;
        
        DB::table('announcement_views')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'announcement_id' => $id,
            'user_id' => $teacherId,
            'viewed_at' => now(),
        ]);

        return response()->json(['message' => 'Announcement marked as read.']);
    }
}


