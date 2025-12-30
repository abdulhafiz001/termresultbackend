<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Approved - TermResult</title>
    <style>
        /* Lightweight mobile improvements (email-client safe) */
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; }
            .px { padding-left: 16px !important; padding-right: 16px !important; }
            .py { padding-top: 16px !important; padding-bottom: 16px !important; }
            .h1 { font-size: 24px !important; line-height: 1.2 !important; }
            .h2 { font-size: 20px !important; line-height: 1.2 !important; }
            .btn { display: block !important; width: 100% !important; box-sizing: border-box !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f9fafb; font-family: Arial, sans-serif; line-height: 1.6; color: #374151;">
    <!-- Header with Logo -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #111827 0%, #1e293b 100%);">
        <tr>
            <td align="center" style="padding: 32px 0;">
                <table cellpadding="0" cellspacing="0" style="max-width: 600px;">
                    <tr>
                        <td align="center">
                            <!-- Logo -->
                            <img src="https://i.ibb.co/xtnR63Qs/termresult-logo.png" alt="TermResult" style="height: 60px; display: block; margin: 0 auto 16px;">
                            <p style="color: #d1d5db; margin: 0; font-size: 16px;">
                                School Management Platform
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Main Content -->
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table cellpadding="0" cellspacing="0" class="container" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden;">
                    <!-- Celebration Banner -->
                    <tr>
                        <td class="px" style="padding: 32px 32px 0 32px; background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <div style="background-color: rgba(255,255,255,0.2); border-radius: 50%; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                            <span style="color: white; font-size: 40px;">🎉</span>
                                        </div>
                                        <h2 class="h1" style="color: white; margin: 0 0 8px 0; font-size: 28px; font-weight: bold;">
                                            Welcome to TermResult!
                                        </h2>
                                        <p style="color: rgba(255,255,255,0.9); margin: 0; font-size: 18px;">
                                            Your school registration has been approved
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Welcome Message -->
                    <tr>
                        <td class="px py" style="padding: 32px;">
                            <p style="color: #111827; margin: 0 0 24px 0; font-size: 18px; line-height: 1.8;">
                                Congratulations, <strong style="color: #059669;">{{ $school->name }}</strong>! 
                                We're excited to welcome you to TermResult. Your school portal is now active and ready to use.
                            </p>

                            <!-- Landing Page Link -->
                            @if(!empty($links['landing']))
                            <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 4px; margin-bottom: 24px;">
                                <p style="color: #1e3a8a; margin: 0; font-size: 14px;">
                                    🏫 <strong>School Landing Page:</strong>
                                    <a href="{{ $links['landing'] }}" style="color: #2563eb; font-weight: bold; text-decoration: none;">Open your school page</a>
                                </p>
                            </div>
                            @endif
                            
                            <!-- Important Notice -->
                            <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 16px; border-radius: 4px; margin-bottom: 32px;">
                                <p style="color: #065f46; margin: 0; font-size: 15px;">
                                    💡 <strong>Important:</strong> Your admin portal is ready. Please save the login credentials below securely.
                                </p>
                            </div>

                            <!-- Admin Credentials Card -->
                            <div style="background: linear-gradient(to right, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px; padding: 24px; border: 1px solid #e2e8f0; margin-bottom: 32px;">
                                <h3 style="color: #111827; margin: 0 0 20px 0; font-size: 20px; font-weight: bold; display: flex; align-items: center;">
                                    <span style="background-color: #111827; color: white; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 16px;">🔑</span>
                                    Admin Login Credentials
                                </h3>
                                
                                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: white; border-radius: 8px; overflow: hidden;">
                                    <tr>
                                        <td style="padding: 16px; border-bottom: 1px solid #f1f5f9;">
                                            <strong style="color: #4b5563; font-size: 14px;">School Name:</strong>
                                            <div style="color: #111827; font-size: 16px; font-weight: 500; margin-top: 4px;">{{ $school->name }}</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 16px; border-bottom: 1px solid #f1f5f9;">
                                            <strong style="color: #4b5563; font-size: 14px;">Username:</strong>
                                            <div style="color: #111827; font-size: 18px; font-weight: bold; margin-top: 4px; font-family: 'Courier New', monospace;">{{ $adminUsername }}</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 16px;">
                                            <strong style="color: #4b5563; font-size: 14px;">Temporary Password:</strong>
                                            <div style="color: #dc2626; font-size: 18px; font-weight: bold; margin-top: 4px; font-family: 'Courier New', monospace;">{{ $adminPassword }}</div>
                                        </td>
                                    </tr>
                                </table>
                                
                                <div style="background-color: #fef2f2; padding: 12px; border-radius: 6px; margin-top: 16px;">
                                    <p style="color: #991b1b; margin: 0; font-size: 13px; display: flex; align-items: center;">
                                        ⚠️ <strong style="margin-left: 8px;">Security Notice:</strong> This is a temporary password. Please change it immediately after first login.
                                    </p>
                                </div>
                            </div>

                            <!-- Portal Links -->
                            <div style="background-color: #f8fafc; border-radius: 12px; padding: 24px; border: 1px solid #e2e8f0;">
                                <h3 style="color: #111827; margin: 0 0 20px 0; font-size: 20px; font-weight: bold; display: flex; align-items: center;">
                                    <span style="background-color: #3b82f6; color: white; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 16px;">🚪</span>
                                    Portal Access Links
                                </h3>
                                
                                <p style="color: #6b7280; margin: 0 0 20px 0; font-size: 15px;">
                                    Use these links to access your school portals:
                                </p>
                                
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="padding-bottom: 12px;">
                                            <a href="{{ $links['admin'] }}" class="btn" style="display: block; background-color: white; padding: 16px; border-radius: 8px; border: 2px solid #e5e7eb; text-decoration: none; color: #111827; font-weight: bold; transition: all 0.2s;">
                                                <table width="100%">
                                                    <tr>
                                                        <td>
                                                            <div style="color: #111827; font-size: 16px; margin-bottom: 4px;">👨‍💼 School Admin Portal</div>
                                                            <div style="color: #6b7280; font-size: 13px;">Manage school settings, users, and data</div>
                                                        </td>
                                                        <td width="40" align="right">
                                                            <span style="color: #10b981; font-size: 20px;">→</span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 12px;">
                                            <a href="{{ $links['teacher'] }}" class="btn" style="display: block; background-color: white; padding: 16px; border-radius: 8px; border: 2px solid #e5e7eb; text-decoration: none; color: #111827; font-weight: bold; transition: all 0.2s;">
                                                <table width="100%">
                                                    <tr>
                                                        <td>
                                                            <div style="color: #111827; font-size: 16px; margin-bottom: 4px;">👩‍🏫 Teacher Portal</div>
                                                            <div style="color: #6b7280; font-size: 13px;">Upload results, manage classes and students</div>
                                                        </td>
                                                        <td width="40" align="right">
                                                            <span style="color: #10b981; font-size: 20px;">→</span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <a href="{{ $links['student'] }}" class="btn" style="display: block; background-color: white; padding: 16px; border-radius: 8px; border: 2px solid #e5e7eb; text-decoration: none; color: #111827; font-weight: bold; transition: all 0.2s;">
                                                <table width="100%">
                                                    <tr>
                                                        <td>
                                                            <div style="color: #111827; font-size: 16px; margin-bottom: 4px;">🎓 Student Portal</div>
                                                            <div style="color: #6b7280; font-size: 13px;">View results, grades, and academic progress</div>
                                                        </td>
                                                        <td width="40" align="right">
                                                            <span style="color: #10b981; font-size: 20px;">→</span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Next Steps -->
                            <div style="background-color: #eff6ff; padding: 20px; border-radius: 8px; margin-top: 32px; border-left: 4px solid #3b82f6;">
                                <h4 style="color: #1e40af; margin: 0 0 12px 0; font-size: 16px; font-weight: bold;">
                                    📋 Recommended Next Steps:
                                </h4>
                                <ul style="color: #374151; margin: 0; padding-left: 20px; font-size: 15px;">
                                    <li style="margin-bottom: 8px;">1. Login to Admin Portal and change your password</li>
                                    <li style="margin-bottom: 8px;">2. Set up your school profile and academic terms</li>
                                    <li style="margin-bottom: 8px;">3. Add teachers and staff to the system</li>
                                    <li style="margin-bottom: 8px;">4. Create classes and enroll students</li>
                                    <li>5. Explore our <a href="https://termresult.com/guide" style="color: #10b981; font-weight: bold;">Getting Started Guide</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>

                    <!-- Support Section -->
                    <tr>
                        <td style="padding: 24px 32px; background-color: #f8fafc; border-top: 1px solid #e5e7eb;">
                            <table width="100%">
                                <tr>
                                    <td>
                                        <h4 style="color: #111827; margin: 0 0 12px 0; font-size: 16px; font-weight: bold;">Need Help?</h4>
                                        <p style="color: #6b7280; margin: 0 0 16px 0; font-size: 14px;">
                                            Our support team is ready to help you get started.
                                        </p>
                                        <a href="https://termresult.com/support" style="display: inline-block; padding: 10px 20px; background-color: #111827; color: white; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: bold;">
                                            Contact Support
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #111827;">
        <tr>
            <td align="center" style="padding: 32px 20px;">
                <table cellpadding="0" cellspacing="0" style="max-width: 600px;">
                    <tr>
                        <td align="center">
                            <p style="color: #9ca3af; margin: 0 0 8px 0; font-size: 14px;">
                                © {{ date('Y') }} TermResult. All rights reserved.
                            </p>
                            <p style="color: #6b7280; margin: 0; font-size: 13px;">
                                <a href="https://termresult.com" style="color:rgb(16, 47, 185); text-decoration: none;">termresult.com</a>
                                <span style="color: #4b5563;"> | </span>
                                <a href="https://termresult.com/help" style="color:rgb(35, 37, 167); text-decoration: none;">Help Center</a>
                                <span style="color: #4b5563;"> | </span>
                                <a href="https://termresult.com/admin" style="color:rgb(16, 47, 185); text-decoration: none;">Admin Panel</a>
                            </p>
                            <p style="color: #4b5563; margin: 16px 0 0 0; font-size: 12px;">
                                This is an automated message. Please do not reply to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>