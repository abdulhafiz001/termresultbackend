<?php

use App\Http\Controllers\Platform\ApprovalController;
use App\Http\Controllers\Public\SchoolRegistrationController;
use App\Http\Controllers\Public\LandingPageController as PublicLandingPageController;
use App\Http\Controllers\Public\TenantResolveController;
use App\Http\Controllers\Public\PaystackWebhookController;
use App\Http\Controllers\Public\TrafficController as PublicTrafficController;
use App\Http\Controllers\Public\ReferralsController as PublicReferralsController;
use App\Http\Controllers\Public\ContactController as PublicContactController;
use App\Http\Controllers\Tenant\AuthController as TenantAuthController;
use App\Http\Controllers\Platform\AdminAuthController as PlatformAdminAuthController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\OnboardingFlowController as PlatformOnboardingFlowController;
use App\Http\Controllers\Platform\SchoolsController as PlatformSchoolsController;
use App\Http\Controllers\Platform\TrafficController as PlatformTrafficController;
use App\Http\Controllers\Platform\CacheController as PlatformCacheController;
use App\Http\Controllers\Platform\ReferralsController as PlatformReferralsController;
use App\Http\Controllers\Platform\ContactMessagesController as PlatformContactMessagesController;
use App\Http\Controllers\Platform\AdminsController as PlatformAdminsController;
use App\Http\Controllers\Platform\AdminActivitiesController as PlatformAdminActivitiesController;
use App\Http\Controllers\Tenant\Admin\AcademicController;
use App\Http\Controllers\Tenant\Admin\AnnouncementsController;
use App\Http\Controllers\Tenant\Admin\ClassesController;
use App\Http\Controllers\Tenant\Admin\ComplaintsController as AdminComplaintsController;
use App\Http\Controllers\Tenant\Admin\FeeRulesController;
use App\Http\Controllers\Tenant\Admin\PaymentsController as AdminPaymentsController;
use App\Http\Controllers\Tenant\Admin\PaymentSettingsController as AdminPaymentSettingsController;
use App\Http\Controllers\Tenant\Admin\SchoolConfigController;
use App\Http\Controllers\Tenant\Admin\StorageController as AdminStorageController;
use App\Http\Controllers\Tenant\Admin\DashboardController;
use App\Http\Controllers\Tenant\Admin\StudentsController;
use App\Http\Controllers\Tenant\Admin\SubjectsController;
use App\Http\Controllers\Tenant\Admin\TeachersController;
use App\Http\Controllers\Tenant\Admin\TimetableController as AdminTimetableController;
use App\Http\Controllers\Tenant\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Tenant\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Tenant\Admin\ResultsController as AdminResultsController;
use App\Http\Controllers\Tenant\Admin\PromotionRulesController;
use App\Http\Controllers\Tenant\Admin\GradingConfigsController;
use App\Http\Controllers\Tenant\Admin\LandingPageController as AdminLandingPageController;
use App\Http\Controllers\Tenant\Admin\AdministratorsController;
use App\Http\Controllers\Tenant\Admin\TeacherActivitiesController;
use App\Http\Controllers\Tenant\Admin\ExamQuestionsController as AdminExamQuestionsController;
use App\Http\Controllers\Tenant\Admin\ExamsController as AdminExamsController;
use App\Http\Controllers\Tenant\Teacher\MyClassesController as TeacherMyClassesController;
use App\Http\Controllers\Tenant\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Tenant\Teacher\ScoresController as TeacherScoresController;
use App\Http\Controllers\Tenant\Teacher\StudentsController as TeacherStudentsController;
use App\Http\Controllers\Tenant\Teacher\AttendanceController as TeacherAttendanceController;
use App\Http\Controllers\Tenant\Teacher\AnnouncementsController as TeacherAnnouncementsController;
use App\Http\Controllers\Tenant\Teacher\TimetableController as TeacherTimetableController;
use App\Http\Controllers\Tenant\Teacher\ProfileController as TeacherProfileController;
use App\Http\Controllers\Tenant\Teacher\ExamSubmissionsController as TeacherExamSubmissionsController;
use App\Http\Controllers\Tenant\Teacher\ExamsController as TeacherExamsController;
use App\Http\Controllers\Tenant\Teacher\AcademicController as TeacherAcademicController;
use App\Http\Controllers\Tenant\Teacher\AssignmentsController as TeacherAssignmentsController;
use App\Http\Controllers\Tenant\Teacher\StudyMaterialsController as TeacherStudyMaterialsController;
use App\Http\Controllers\Tenant\Teacher\SubjectsController as TeacherSubjectsController;
use App\Http\Controllers\Tenant\Student\ComplaintsController as StudentComplaintsController;
use App\Http\Controllers\Tenant\Student\MeController as StudentMeController;
use App\Http\Controllers\Tenant\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Tenant\Student\AcademicController as StudentAcademicController;
use App\Http\Controllers\Tenant\Student\StudyMaterialsController as StudentStudyMaterialsController;
use App\Http\Controllers\Tenant\Student\ResultsController as StudentResultsController;
use App\Http\Controllers\Tenant\Student\ReportCardController as StudentReportCardController;
use App\Http\Controllers\Tenant\Student\SubjectsController as StudentSubjectsController;
use App\Http\Controllers\Tenant\Student\AnnouncementsController as StudentAnnouncementsController;
use App\Http\Controllers\Tenant\Student\PaymentsController as StudentPaymentsController;
use App\Http\Controllers\Tenant\Student\ProgressController as StudentProgressController;
use App\Http\Controllers\Tenant\Student\TimetableController as StudentTimetableController;
use App\Http\Controllers\Tenant\Student\ExamsController as StudentExamsController;
use App\Http\Controllers\Tenant\Student\AssignmentsController as StudentAssignmentsController;
use Illuminate\Support\Facades\Route;

Route::get('/test/api', function () {
    return response()->json([
        'message' => 'TermResult API is running',
        'status' => 'success',
        'version' => '1.0.0',
    ]);
});

Route::prefix('public')->group(function () {
    Route::post('/schools/register', [SchoolRegistrationController::class, 'register'])
        ->middleware('throttle:20,1');
    Route::get('/tenants/resolve', [TenantResolveController::class, 'resolve']);
    // Adblock-safe aliases (some extensions block URLs containing "traffic" or similar).
    Route::get('/site-info', [TenantResolveController::class, 'resolve']);
    Route::get('/landing-content', [PublicLandingPageController::class, 'getContent']);
    Route::get('/site-content', [PublicLandingPageController::class, 'getContent']);
    Route::post('/traffic', [PublicTrafficController::class, 'store'])->middleware('throttle:120,1');
    Route::post('/beacon', [PublicTrafficController::class, 'store'])->middleware('throttle:120,1');
    Route::post('/referrals', [PublicReferralsController::class, 'store'])->middleware('throttle:30,1');
    Route::post('/contact', [PublicContactController::class, 'store'])->middleware('throttle:2,1');

    // Paystack webhook (tenant derived from metadata.school_subdomain).
    Route::post('/paystack/webhook', [PaystackWebhookController::class, 'handle']);
});

Route::prefix('platform')->group(function () {
    Route::get('/approvals/{token}/accept', [ApprovalController::class, 'accept'])
        ->middleware('signed')
        ->name('platform.approvals.accept');

    Route::get('/approvals/{token}/decline', [ApprovalController::class, 'declineForm'])
        ->middleware('signed')
        ->name('platform.approvals.declineForm');

    Route::post('/approvals/{token}/decline', [ApprovalController::class, 'decline'])
        ->middleware('signed')
        ->name('platform.approvals.decline');
});

// TermResult Super Admin (Platform Admin) API
Route::prefix('platform-admin')->group(function () {
    Route::get('/exists', [PlatformAdminAuthController::class, 'exists']);
    Route::post('/setup', [PlatformAdminAuthController::class, 'setup'])->middleware('throttle:10,1');
    Route::post('/login', [PlatformAdminAuthController::class, 'login'])->middleware('throttle:30,1');

    Route::middleware(['auth:sanctum', 'platform_admin'])->group(function () {
        Route::get('/me', [PlatformAdminAuthController::class, 'me']);
        Route::post('/logout', [PlatformAdminAuthController::class, 'logout']);

        Route::get('/dashboard/stats', [PlatformDashboardController::class, 'stats']);
        Route::get('/onboarding-flow/pdf', [PlatformOnboardingFlowController::class, 'download']);
        Route::get('/traffic/daily', [PlatformTrafficController::class, 'daily']);
        Route::get('/traffic/top-schools', [PlatformTrafficController::class, 'topSchools']);
        Route::post('/cache/clear', [PlatformCacheController::class, 'clear']);

        Route::get('/referrals', [PlatformReferralsController::class, 'index']);
        Route::post('/referrals/{id}/status', [PlatformReferralsController::class, 'updateStatus']);

        Route::get('/contact-messages', [PlatformContactMessagesController::class, 'index']);
        Route::post('/contact-messages/{id}/reply', [PlatformContactMessagesController::class, 'reply']);

        // Settings: platform admins & activities (used by Platform Settings UI)
        Route::get('/admins', [PlatformAdminsController::class, 'index']);
        Route::post('/admins', [PlatformAdminsController::class, 'store']);
        Route::delete('/admins/{id}', [PlatformAdminsController::class, 'deactivate']);
        Route::get('/admin-activities', [PlatformAdminActivitiesController::class, 'index']);

        Route::get('/schools', [PlatformSchoolsController::class, 'index']);
        Route::get('/schools/pending', [PlatformSchoolsController::class, 'pending']);
        Route::get('/schools/{id}', [PlatformSchoolsController::class, 'show']);
        Route::post('/schools/{id}/approve', [PlatformSchoolsController::class, 'approve']);
        Route::post('/schools/{id}/decline', [PlatformSchoolsController::class, 'decline']);
        Route::post('/schools/{id}/purge', [PlatformSchoolsController::class, 'purge']);
        Route::post('/schools/{id}/restrict-login', [PlatformSchoolsController::class, 'restrictLogin']);
        Route::post('/schools/{id}/restrict-site', [PlatformSchoolsController::class, 'restrictSite']);
        Route::post('/schools/{id}/reset-admin-password', [PlatformSchoolsController::class, 'resetAdminPassword']);
        Route::post('/schools/{id}/storage-quota', [PlatformSchoolsController::class, 'updateStorageQuota']);
    });
});

Route::prefix('tenant')->group(function () {
    // IdentifyTenant middleware is prepended globally to the API group in bootstrap/app.php.
    Route::post('/auth/login', [TenantAuthController::class, 'login'])
        ->middleware('throttle:60,1');
    Route::post('/auth/verify-admission', [TenantAuthController::class, 'verifyAdmissionNumber'])
        ->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [TenantAuthController::class, 'forgotPassword'])
        ->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'role:school_admin'])
        ->prefix('admin')
        ->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'stats']);
            Route::get('/school-config', [SchoolConfigController::class, 'show']);
            Route::post('/school-config', [SchoolConfigController::class, 'update']);
            Route::post('/school-config/logo', [SchoolConfigController::class, 'uploadLogo']);
            Route::delete('/school-config/logo', [SchoolConfigController::class, 'deleteLogo']);

            // Storage usage + backup/cleanup (tenant-scoped)
            Route::get('/storage/usage', [AdminStorageController::class, 'usage']);
            Route::post('/storage/backup', [AdminStorageController::class, 'backupInit']);
            Route::get('/storage/backup/{token}/download', [AdminStorageController::class, 'backupDownload']);
            Route::post('/storage/cleanup', [AdminStorageController::class, 'cleanup']);

            // Storage backup/cleanup for past academic session/term (files only)
            Route::post('/storage/backup-session-term', [AdminStorageController::class, 'backupSessionTerm']);
            Route::get('/storage/backup-scope/{token}/download', [AdminStorageController::class, 'backupScopeDownload']);
            Route::post('/storage/cleanup-scope', [AdminStorageController::class, 'cleanupScope']);

            // School landing page management.
            Route::get('/landing-page', [AdminLandingPageController::class, 'show']);
            Route::put('/landing-page', [AdminLandingPageController::class, 'update']);

            Route::get('/academic/status', [AcademicController::class, 'status']);
            Route::get('/academic/sessions', [AcademicController::class, 'list']);
            Route::post('/academic/sessions', [AcademicController::class, 'createSession']);
            Route::post('/academic/sessions/{id}/set-current', [AcademicController::class, 'setCurrentSession']);
            Route::post('/academic/sessions/{sessionId}/terms', [AcademicController::class, 'upsertTerm']);
            Route::post('/academic/terms/{termId}/set-current', [AcademicController::class, 'setCurrentTerm']);

            Route::get('/classes', [ClassesController::class, 'index']);
            Route::post('/classes', [ClassesController::class, 'store']);
            Route::put('/classes/{id}', [ClassesController::class, 'update']);
            Route::delete('/classes/{id}', [ClassesController::class, 'destroy']);

            Route::get('/subjects', [SubjectsController::class, 'index']);
            Route::post('/subjects', [SubjectsController::class, 'store']);
            Route::put('/subjects/{id}', [SubjectsController::class, 'update']);
            Route::delete('/subjects/{id}', [SubjectsController::class, 'destroy']);

            Route::get('/teachers', [TeachersController::class, 'index']);
            Route::post('/teachers', [TeachersController::class, 'store']);
            Route::put('/teachers/{id}', [TeachersController::class, 'update']);
            Route::delete('/teachers/{id}', [TeachersController::class, 'destroy']);

            Route::get('/students', [StudentsController::class, 'index']);
            Route::post('/students', [StudentsController::class, 'store']);
            Route::get('/students/export', [StudentsController::class, 'export']);
            Route::post('/students/import', [StudentsController::class, 'import']);
            Route::get('/students/{id}', [StudentsController::class, 'show']);
            Route::put('/students/{id}', [StudentsController::class, 'update']);
            Route::delete('/students/{id}', [StudentsController::class, 'destroy']);

            Route::get('/announcements', [AnnouncementsController::class, 'index']);
            Route::post('/announcements', [AnnouncementsController::class, 'store']);
            Route::put('/announcements/{id}', [AnnouncementsController::class, 'update']);
            Route::delete('/announcements/{id}', [AnnouncementsController::class, 'destroy']);

            Route::get('/complaints', [AdminComplaintsController::class, 'index']);
            Route::get('/complaints/unread-count', [AdminComplaintsController::class, 'unreadCount']);
            Route::put('/complaints/{id}', [AdminComplaintsController::class, 'update']);

            Route::get('/fees/rules', [FeeRulesController::class, 'index']);
            Route::post('/fees/rules', [FeeRulesController::class, 'store']);
            Route::put('/fees/rules/{id}', [FeeRulesController::class, 'update']);
            Route::delete('/fees/rules/{id}', [FeeRulesController::class, 'destroy']);

            // Payments: settings (manual vs automatic).
            Route::get('/payments/settings', [AdminPaymentSettingsController::class, 'show']);
            Route::put('/payments/settings', [AdminPaymentSettingsController::class, 'update']);
            Route::get('/payments/paystack/banks', [AdminPaymentSettingsController::class, 'paystackBanks']);
            Route::post('/payments/paystack/resolve-account', [AdminPaymentSettingsController::class, 'resolvePaystackAccount']);
            Route::post('/payments/paystack/create-subaccount', [AdminPaymentSettingsController::class, 'createPaystackSubaccount']);

            // Payments: records + stats + manual record.
            Route::get('/payments', [AdminPaymentsController::class, 'index']);
            Route::get('/payments/stats', [AdminPaymentsController::class, 'stats']);
            Route::post('/payments/manual-record', [AdminPaymentsController::class, 'recordManual']);

            Route::get('/timetables', [AdminTimetableController::class, 'index']);
            Route::post('/timetables', [AdminTimetableController::class, 'store']);
            Route::put('/timetables/{id}', [AdminTimetableController::class, 'update']);
            Route::delete('/timetables/{id}', [AdminTimetableController::class, 'destroy']);

            Route::get('/attendance', [AdminAttendanceController::class, 'index']);

            Route::get('/results', [AdminResultsController::class, 'index']);
            Route::get('/results/students/{studentId}', [AdminResultsController::class, 'showStudentResults']);

            Route::get('/profile', [AdminProfileController::class, 'show']);
            Route::put('/profile', [AdminProfileController::class, 'update']);
            Route::post('/profile/change-password', [AdminProfileController::class, 'changePassword']);

            // Promotion rules + run promotion (3rd term only).
            Route::get('/promotion-rules', [PromotionRulesController::class, 'index']);
            Route::post('/promotion-rules', [PromotionRulesController::class, 'store']);
            Route::put('/promotion-rules/{id}', [PromotionRulesController::class, 'update']);
            Route::delete('/promotion-rules/{id}', [PromotionRulesController::class, 'destroy']);
            Route::post('/promotion-rules/run', [PromotionRulesController::class, 'run']);

            // Grading configurations.
            Route::get('/grading-configs', [GradingConfigsController::class, 'index']);
            Route::post('/grading-configs', [GradingConfigsController::class, 'store']);
            Route::put('/grading-configs/{id}', [GradingConfigsController::class, 'update']);
            Route::delete('/grading-configs/{id}', [GradingConfigsController::class, 'destroy']);

            // Manage additional administrators.
            Route::get('/administrators', [AdministratorsController::class, 'index']);
            Route::post('/administrators', [AdministratorsController::class, 'store']);
            Route::put('/administrators/{id}', [AdministratorsController::class, 'update']);
            Route::delete('/administrators/{id}', [AdministratorsController::class, 'destroy']);

            // Teacher activities.
            Route::get('/teacher-activities', [TeacherActivitiesController::class, 'index']);

            // Exams - question submissions review.
            Route::get('/exam-questions', [AdminExamQuestionsController::class, 'index']);
            Route::get('/exam-questions/{id}/paper', [AdminExamQuestionsController::class, 'downloadPaper']);
            Route::get('/exam-questions/{id}/source', [AdminExamQuestionsController::class, 'downloadSource']);
            Route::post('/exam-questions/{id}/approve', [AdminExamQuestionsController::class, 'approve']);
            Route::post('/exam-questions/{id}/reject', [AdminExamQuestionsController::class, 'reject']);
            Route::delete('/exam-questions/{id}', [AdminExamQuestionsController::class, 'delete']);

            // Exams - manage.
            Route::get('/exams', [AdminExamsController::class, 'index']);
            Route::post('/exams/{id}/start', [AdminExamsController::class, 'start']);
            Route::post('/exams/{id}/end', [AdminExamsController::class, 'end']);
            Route::get('/exams/{id}/monitor', [AdminExamsController::class, 'monitor']);
        });

    Route::middleware(['auth:sanctum', 'role:teacher'])
        ->prefix('teacher')
        ->group(function () {
            Route::get('/dashboard', [TeacherDashboardController::class, 'stats']);
            Route::get('/academic/sessions', [TeacherAcademicController::class, 'sessions']);
            Route::get('/my-classes', [TeacherMyClassesController::class, 'index']);
            Route::get('/form-classes', [TeacherMyClassesController::class, 'formClasses']);
            Route::get('/subjects', [TeacherSubjectsController::class, 'index']);
            Route::get('/students', [TeacherStudentsController::class, 'index']);
            Route::post('/students', [TeacherStudentsController::class, 'store']);
            Route::put('/students/{id}', [TeacherStudentsController::class, 'update']);
            Route::get('/scores', [TeacherScoresController::class, 'listForClassSubject']);
            Route::post('/scores', [TeacherScoresController::class, 'upsert']);
            Route::get('/announcements', [TeacherAnnouncementsController::class, 'index']);
            Route::get('/announcements/unread-count', [TeacherAnnouncementsController::class, 'unreadCount']);
            Route::post('/announcements/{id}/mark-read', [TeacherAnnouncementsController::class, 'markAsRead']);
            Route::get('/attendance', [TeacherAttendanceController::class, 'index']);
            Route::post('/attendance', [TeacherAttendanceController::class, 'store']);

            Route::get('/timetable', [TeacherTimetableController::class, 'index']);

            Route::get('/profile', [TeacherProfileController::class, 'show']);
            Route::put('/profile', [TeacherProfileController::class, 'update']);
            Route::post('/profile/change-password', [TeacherProfileController::class, 'changePassword']);

            // Exams (teacher)
            Route::get('/exam-submissions', [TeacherExamSubmissionsController::class, 'index']);
            Route::post('/exam-submissions', [TeacherExamSubmissionsController::class, 'store']);
            Route::get('/exam-submissions/{id}/paper', [TeacherExamSubmissionsController::class, 'downloadPaper']);
            Route::get('/exam-submissions/{id}/source', [TeacherExamSubmissionsController::class, 'downloadSource']);

            Route::get('/exams', [TeacherExamsController::class, 'index']);
            Route::post('/exams/{examId}/answer-key', [TeacherExamsController::class, 'setAnswerKey']);
            Route::post('/exams/{examId}/release-answer-slip', [TeacherExamsController::class, 'releaseAnswerSlip']);
            Route::get('/exams/{examId}/attempts', [TeacherExamsController::class, 'attempts']);
            Route::get('/attempts/{attemptId}', [TeacherExamsController::class, 'attemptDetail']);
            Route::post('/attempts/{attemptId}/mark', [TeacherExamsController::class, 'markAttempt']);
            Route::get('/attempts/{attemptId}/answer-slip', [TeacherExamsController::class, 'answerSlipPdf']);

            // Assignments (teacher)
            Route::get('/assignments', [TeacherAssignmentsController::class, 'index']);
            Route::post('/assignments', [TeacherAssignmentsController::class, 'store']);
            Route::get('/assignments/submissions', [TeacherAssignmentsController::class, 'getSubmissions']);
            Route::post('/assignments/submissions/{submissionId}/mark', [TeacherAssignmentsController::class, 'markSubmission']);

            // Study materials (teacher)
            Route::get('/study-materials', [TeacherStudyMaterialsController::class, 'index']);
            Route::post('/study-materials', [TeacherStudyMaterialsController::class, 'store']);
            Route::get('/study-materials/{id}/download', [TeacherStudyMaterialsController::class, 'download']);
            Route::delete('/study-materials/{id}', [TeacherStudyMaterialsController::class, 'destroy']);

            // Import students (teacher)
            Route::post('/students/import', [TeacherStudentsController::class, 'import']);
        });

    Route::middleware(['auth:sanctum', 'role:student'])
        ->prefix('student')
        ->group(function () {
            Route::get('/dashboard', [StudentDashboardController::class, 'stats']);
            Route::get('/academic/sessions', [StudentAcademicController::class, 'sessions']);
            Route::get('/me', [StudentMeController::class, 'show']);
            Route::put('/me', [StudentMeController::class, 'update']);
            Route::post('/me/change-password', [StudentMeController::class, 'changePassword']);
            Route::get('/results', [StudentResultsController::class, 'index']);
            Route::get('/report-card', [StudentReportCardController::class, 'download']);
            Route::get('/subjects', [StudentSubjectsController::class, 'index']);
            Route::get('/timetable', [StudentTimetableController::class, 'index']);
            Route::get('/progress', [StudentProgressController::class, 'index']);
            Route::get('/announcements', [StudentAnnouncementsController::class, 'index']);
            Route::get('/announcements/unread-count', [StudentAnnouncementsController::class, 'unreadCount']);
            Route::post('/announcements/{id}/mark-read', [StudentAnnouncementsController::class, 'markAsRead']);
            Route::get('/complaints', [StudentComplaintsController::class, 'index']);
            Route::post('/complaints', [StudentComplaintsController::class, 'store']);

            Route::get('/fees', [StudentPaymentsController::class, 'feeSummary']);
            Route::post('/payments/initialize', [StudentPaymentsController::class, 'initialize']);
            Route::post('/payments/confirm', [StudentPaymentsController::class, 'confirm']);
            Route::get('/payments/{id}/receipt', [StudentPaymentsController::class, 'receipt']);

            // Exams (student)
            Route::get('/exams/answer-slips', [StudentExamsController::class, 'answerSlips']);
            Route::post('/exams/resolve-code', [StudentExamsController::class, 'resolveCode']);
            Route::post('/exams/{examId}/begin', [StudentExamsController::class, 'begin']);
            Route::get('/exams/{examId}/paper', [StudentExamsController::class, 'paper']);
            Route::post('/exams/{examId}/heartbeat', [StudentExamsController::class, 'heartbeat']);
            Route::post('/exams/{examId}/save', [StudentExamsController::class, 'saveAnswers']);
            Route::post('/exams/{examId}/submit', [StudentExamsController::class, 'submit']);
            Route::get('/attempts/{attemptId}/answer-slip', [StudentExamsController::class, 'answerSlipPdf']);

            // Assignments (student)
            Route::get('/assignments', [StudentAssignmentsController::class, 'index']);
            Route::get('/assignments/{assignmentId}', [StudentAssignmentsController::class, 'show']);
            Route::post('/assignments/{assignmentId}/submit', [StudentAssignmentsController::class, 'submit']);

            // Study materials (student)
            Route::get('/study-materials', [StudentStudyMaterialsController::class, 'index']);
            Route::get('/study-materials/{id}/download', [StudentStudyMaterialsController::class, 'download']);
        });
});


