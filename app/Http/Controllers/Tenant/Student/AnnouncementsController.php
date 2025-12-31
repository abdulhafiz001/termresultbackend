<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class AnnouncementsController extends Controller
{
    public function index(Request $request)
    {
        $studentId = $request->user()->id;
        $tenantId = TenantContext::id();
        $profile = TenantDB::table('student_profiles')->where('user_id', $studentId)->first();
        $classId = $profile?->current_class_id;

        $query = TenantDB::table('announcements')->orderByDesc('published_at');

        $query->where(function ($w) use ($classId) {
            $w->where('for_all_students', true);

            if ($classId) {
                $driver = Config::get('database.connections.'.DB::getDefaultConnection().'.driver');
                if (in_array($driver, ['mysql', 'mariadb'], true)) {
                    $w->orWhereRaw('JSON_CONTAINS(class_ids, ?)', [json_encode((int) $classId)]);
                } else {
                    // Fallback for sqlite/dev
                    $w->orWhere('class_ids', 'like', '%"'.(int) $classId.'"%');
                }
            }
        });

        $items = $query->limit(200)->get()->map(function ($a) use ($studentId) {
            $isRead = TenantDB::table('announcement_views')
                ->where('announcement_id', $a->id)
                ->where('user_id', $studentId)
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
        $studentId = $request->user()->id;
        $tenantId = TenantContext::id();
        $profile = TenantDB::table('student_profiles')->where('user_id', $studentId)->first();
        $classId = $profile?->current_class_id;

        $cacheKey = "announcements:unread_count:student:{$studentId}:class:" . (int) ($classId ?? 0);
        $total = Cache::remember($cacheKey, 30, function () use ($classId, $studentId, $tenantId) {
            $query = TenantDB::table('announcements');

            $query->where(function ($w) use ($classId) {
                $w->where('for_all_students', true);

                if ($classId) {
                    $driver = Config::get('database.connections.'.DB::getDefaultConnection().'.driver');
                    if (in_array($driver, ['mysql', 'mariadb'], true)) {
                        $w->orWhereRaw('JSON_CONTAINS(class_ids, ?)', [json_encode((int) $classId)]);
                    } else {
                        $w->orWhere('class_ids', 'like', '%"'.(int) $classId.'"%');
                    }
                }
            });

            return $query->whereNotIn('id', function ($subQuery) use ($studentId, $tenantId) {
                $subQuery->select('announcement_id')
                    ->from('announcement_views')
                    ->where('user_id', $studentId)
                    ->where('tenant_id', $tenantId);
            })->count();
        });

        return response()->json(['count' => $total]);
    }

    public function markAsRead(Request $request, int $id)
    {
        $studentId = $request->user()->id;
        $tenantId = TenantContext::id();
        $profile = TenantDB::table('student_profiles')->where('user_id', $studentId)->first();
        $classId = $profile?->current_class_id;
        
        DB::table('announcement_views')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'announcement_id' => $id,
            'user_id' => $studentId,
            'viewed_at' => now(),
        ]);

        Cache::forget("announcements:unread_count:student:{$studentId}:class:" . (int) ($classId ?? 0));

        return response()->json(['message' => 'Announcement marked as read.']);
    }
}


