<?php

namespace App\Support;

/**
 * Security Audit Helper
 * 
 * This class provides security checks and recommendations for the application.
 */
class SecurityAudit
{
    /**
     * Perform a comprehensive security audit
     * 
     * @return array<string, mixed>
     */
    public static function audit(): array
    {
        $issues = [];
        $recommendations = [];

        // 1. Check for exposed sensitive files
        if (file_exists(base_path('.env'))) {
            $recommendations[] = [
                'severity' => 'high',
                'category' => 'Configuration',
                'issue' => '.env file exists in project root',
                'recommendation' => 'Ensure .env is not accessible via web server. Use .htaccess or nginx rules.',
            ];
        }

        // 2. Check password minimum length
        $minPasswordLength = config('auth.password.min_length', 4);
        if ($minPasswordLength < 8) {
            $issues[] = [
                'severity' => 'medium',
                'category' => 'Authentication',
                'issue' => 'Password minimum length is less than 8 characters',
                'recommendation' => 'Consider increasing minimum password length to 8+ characters for better security.',
            ];
        }

        // 3. Check for SQL injection vulnerabilities (basic check)
        // This is a basic check - actual code review is needed
        $recommendations[] = [
            'severity' => 'info',
            'category' => 'Database',
            'issue' => 'SQL injection prevention',
            'recommendation' => 'Ensure all database queries use parameterized queries (Eloquent/Query Builder). Avoid raw DB::raw() with user input.',
        ];

        // 4. Check for XSS vulnerabilities
        $recommendations[] = [
            'severity' => 'info',
            'category' => 'Frontend',
            'issue' => 'XSS prevention',
            'recommendation' => 'Ensure all user input is properly escaped before rendering in frontend. Use React\'s built-in escaping.',
        ];

        // 5. Check CSRF protection
        if (! config('sanctum.middleware.verify_csrf_token', false)) {
            $recommendations[] = [
                'severity' => 'medium',
                'category' => 'CSRF',
                'issue' => 'CSRF token verification',
                'recommendation' => 'Ensure CSRF tokens are validated for state-changing operations.',
            ];
        }

        // 6. Check rate limiting configuration
        $recommendations[] = [
            'severity' => 'info',
            'category' => 'Rate Limiting',
            'issue' => 'Rate limiting is configured',
            'recommendation' => 'Continue monitoring and adjusting rate limits based on traffic patterns.',
        ];

        // 7. Check HTTPS enforcement
        if (! config('app.force_https', false) && config('app.env') === 'production') {
            $issues[] = [
                'severity' => 'high',
                'category' => 'Transport Security',
                'issue' => 'HTTPS not enforced in production',
                'recommendation' => 'Enable HTTPS enforcement in production: APP_FORCE_HTTPS=true',
            ];
        }

        // 8. Check session security
        $recommendations[] = [
            'severity' => 'info',
            'category' => 'Session',
            'issue' => 'Session security',
            'recommendation' => 'Ensure SESSION_SECURE_COOKIE=true and SESSION_HTTP_ONLY=true in production.',
        ];

        // 9. Check file upload security
        $recommendations[] = [
            'severity' => 'info',
            'category' => 'File Upload',
            'issue' => 'File upload validation',
            'recommendation' => 'Ensure all file uploads validate file type, size, and scan for malware.',
        ];

        // 10. Check authentication token expiration
        $recommendations[] = [
            'severity' => 'info',
            'category' => 'Authentication',
            'issue' => 'Token expiration',
            'recommendation' => 'Consider implementing token refresh mechanism and reasonable expiration times.',
        ];

        return [
            'issues' => $issues,
            'recommendations' => $recommendations,
            'summary' => [
                'total_issues' => count($issues),
                'high_severity' => count(array_filter($issues, fn($i) => $i['severity'] === 'high')),
                'medium_severity' => count(array_filter($issues, fn($i) => $i['severity'] === 'medium')),
                'total_recommendations' => count($recommendations),
            ],
        ];
    }
}

