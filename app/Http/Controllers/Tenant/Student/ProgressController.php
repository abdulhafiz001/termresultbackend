<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $profile = TenantDB::table('student_profiles')->where('user_id', $user->id)->first();
        if (!$profile) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        $class = $profile->current_class_id 
            ? TenantDB::table('classes')->where('id', $profile->current_class_id)->first() 
            : null;

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();

        // Get all sessions for this student's results
        $sessionsWithResults = TenantDB::table('student_scores')
            ->join('academic_sessions', function ($j) {
                $j->on('student_scores.academic_session_id', '=', 'academic_sessions.id')
                    ->on('academic_sessions.tenant_id', '=', 'student_scores.tenant_id');
            })
            ->where('student_scores.student_id', $user->id)
            ->select('academic_sessions.id', 'academic_sessions.name', 'academic_sessions.start_date')
            ->distinct()
            ->orderBy('academic_sessions.start_date', 'desc')
            ->get();

        $progressData = [];

        foreach ($sessionsWithResults as $session) {
            $terms = TenantDB::table('terms')
                ->where('academic_session_id', $session->id)
                ->orderBy('id')
                ->get();

            $sessionData = [
                'session_id' => $session->id,
                'session_name' => $session->name,
                'terms' => [],
            ];

            foreach ($terms as $term) {
                // IMPORTANT: This endpoint must NOT expose detailed subject scores/grades,
                // because students could bypass "results restriction" by checking progress.
                // We only return safe aggregates (term averages and counts).
                $stats = TenantDB::table('student_scores')
                    ->where('student_id', $user->id)
                    ->where('academic_session_id', $session->id)
                    ->where('term_id', $term->id)
                    ->whereNotNull('total')
                    ->selectRaw('COUNT(*) as subjects_count, AVG(total) as average')
                    ->first();

                $subjectsCount = (int) ($stats->subjects_count ?? 0);
                $average = (float) ($stats->average ?? 0);

                if ($subjectsCount <= 0) {
                    continue; // Skip terms with no results
                }

                // Calculate position in class
                $position = null;
                $totalStudents = null;
                if ($class) {
                    $classStudents = TenantDB::table('student_profiles')
                        ->where('current_class_id', $class->id)
                        ->pluck('user_id');

                    $classAverages = TenantDB::table('student_scores')
                        ->whereIn('student_id', $classStudents)
                        ->where('academic_session_id', $session->id)
                        ->where('term_id', $term->id)
                        ->select('student_id', DB::raw('AVG(total) as avg_score'))
                        ->groupBy('student_id')
                        ->orderByDesc('avg_score')
                        ->get();

                    $totalStudents = $classAverages->count();
                    
                    $positionIndex = $classAverages->search(function ($item) use ($user) {
                        return $item->student_id === $user->id;
                    });
                    
                    $position = $positionIndex !== false ? $positionIndex + 1 : null;
                }

                $sessionData['terms'][] = [
                    'term_id' => $term->id,
                    'term_name' => $term->name,
                    'average' => round($average, 1),
                    'position' => $position,
                    'total_students' => $totalStudents,
                    'subjects_count' => $subjectsCount,
                ];
            }

            if (count($sessionData['terms']) > 0) {
                $progressData[] = $sessionData;
            }
        }

        // Calculate term-over-term improvement
        $improvements = $this->calculateImprovements($progressData);

        // Generate recommendations based on performance
        $recommendations = $this->generateRecommendations($progressData);

        return response()->json([
            'data' => [
                'student' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'admission_number' => $user->admission_number,
                    'class' => $class?->name,
                ],
                'current_session' => $currentSession ? [
                    'id' => $currentSession->id,
                    'name' => $currentSession->name,
                ] : null,
                'progress' => $progressData,
                'improvements' => $improvements,
                'recommendations' => $recommendations,
            ],
        ]);
    }

    private function calculateImprovements(array $progressData): array
    {
        $allTerms = [];
        
        // Flatten all terms
        foreach ($progressData as $session) {
            foreach ($session['terms'] as $term) {
                $allTerms[] = [
                    'session' => $session['session_name'],
                    'term' => $term['term_name'],
                    'average' => $term['average'],
                ];
            }
        }

        if (count($allTerms) < 2) {
            return [];
        }

        $improvements = [];
        for ($i = 1; $i < count($allTerms); $i++) {
            $current = $allTerms[$i - 1]; // Most recent
            $previous = $allTerms[$i];
            
            $change = $current['average'] - $previous['average'];
            $improvements[] = [
                'from' => $previous['session'] . ' - ' . $previous['term'],
                'to' => $current['session'] . ' - ' . $current['term'],
                'change' => round($change, 1),
                'percentage_change' => $previous['average'] > 0 
                    ? round(($change / $previous['average']) * 100, 1) 
                    : 0,
            ];
        }

        return $improvements;
    }

    private function generateRecommendations(array $progressData): array
    {
        $recommendations = [
            'strengths' => [],
            'areas_for_growth' => [],
        ];

        if (empty($progressData)) {
            return $recommendations;
        }

        // Get the most recent term data
        $latestSession = $progressData[0] ?? null;
        $latestTerm = $latestSession['terms'][count($latestSession['terms']) - 1] ?? null;

        if (!$latestTerm) {
            return $recommendations;
        }

        // Add general recommendations
        if (($latestTerm['average'] ?? 0) >= 80) {
            $recommendations['strengths'][] = "Outstanding overall average - you're on track for academic excellence!";
        } elseif (($latestTerm['average'] ?? 0) >= 70) {
            $recommendations['strengths'][] = "Good academic standing - continue your consistent effort.";
        } elseif (($latestTerm['average'] ?? 0) < 60) {
            $recommendations['areas_for_growth'][] = "Your average needs improvement - set up a study schedule and seek help.";
        }

        return $recommendations;
    }
}

