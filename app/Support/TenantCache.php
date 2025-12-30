<?php

namespace App\Support;

use App\Models\School;

class TenantCache
{
    private static function prefixForSchoolId(int $schoolId): string
    {
        return "tr:tenant:{$schoolId}";
    }

    public static function schoolConfigKey(int $schoolId): string
    {
        return self::prefixForSchoolId($schoolId) . ':school_config';
    }

    public static function landingPagePublicKey(int $schoolId): string
    {
        return self::prefixForSchoolId($schoolId) . ':landing_page:public';
    }

    public static function landingPageAdminKey(int $schoolId): string
    {
        return self::prefixForSchoolId($schoolId) . ':landing_page:admin';
    }

    public static function academicSessionsKey(int $schoolId): string
    {
        return self::prefixForSchoolId($schoolId) . ':academic:sessions';
    }

    public static function adminClassesKey(int $schoolId): string
    {
        return self::prefixForSchoolId($schoolId) . ':admin:classes';
    }

    public static function adminSubjectsKey(int $schoolId): string
    {
        return self::prefixForSchoolId($schoolId) . ':admin:subjects';
    }

    public static function teacherScoresListKey(int $schoolId, int $classId, int $subjectId, int $sessionId, int $termId): string
    {
        return self::prefixForSchoolId($schoolId) . ":teacher:scores:list:class={$classId}:subject={$subjectId}:session={$sessionId}:term={$termId}";
    }

    public static function studentResultsKey(int $schoolId, int $studentId, int $sessionId, int $termId): string
    {
        return self::prefixForSchoolId($schoolId) . ":student:results:student={$studentId}:session={$sessionId}:term={$termId}";
    }

    public static function studentDashboardKey(int $schoolId, int $studentId): string
    {
        return self::prefixForSchoolId($schoolId) . ":student:dashboard:student={$studentId}";
    }

    public static function studentFeesKey(int $schoolId, int $studentId, int $sessionId): string
    {
        return self::prefixForSchoolId($schoolId) . ":student:fees:student={$studentId}:session={$sessionId}";
    }

    public static function publicTenantResolveKey(string $subdomain): string
    {
        $subdomain = strtolower(trim($subdomain));
        return "tr:public:tenant_resolve:{$subdomain}";
    }

    public static function forgetSchoolConfig(School $school): void
    {
        \Cache::forget(self::schoolConfigKey((int) $school->id));
        \Cache::forget(self::publicTenantResolveKey((string) $school->subdomain));
    }

    public static function forgetLandingPage(School $school): void
    {
        \Cache::forget(self::landingPagePublicKey((int) $school->id));
        \Cache::forget(self::landingPageAdminKey((int) $school->id));
    }

    public static function forgetAcademic(School $school): void
    {
        \Cache::forget(self::academicSessionsKey((int) $school->id));
    }

    public static function forgetAdminLists(School $school): void
    {
        \Cache::forget(self::adminClassesKey((int) $school->id));
        \Cache::forget(self::adminSubjectsKey((int) $school->id));
    }

    public static function forgetStudentCaches(School $school, int $studentId, int $sessionId, int $termId): void
    {
        \Cache::forget(self::studentResultsKey((int) $school->id, $studentId, $sessionId, $termId));
        \Cache::forget(self::studentDashboardKey((int) $school->id, $studentId));
    }

    public static function forgetStudentFees(School $school, int $studentId, int $sessionId): void
    {
        \Cache::forget(self::studentFeesKey((int) $school->id, $studentId, $sessionId));
    }
}


