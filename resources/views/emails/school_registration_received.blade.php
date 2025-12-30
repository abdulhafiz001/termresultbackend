<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New School Registration - TermResult</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f9fafb; font-family: Arial, sans-serif; line-height: 1.6; color: #374151;">
    <!-- Header -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #111827;">
        <tr>
            <td align="center" style="padding: 24px 0;">
                <table cellpadding="0" cellspacing="0" style="max-width: 600px;">
                    <tr>
                        <td align="left">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;">
                                <span style="color: #10b981;">Term</span>Result
                            </h1>
                            <p style="color: #9ca3af; margin: 4px 0 0 0; font-size: 14px;">
                                School Management System
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
                <table cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
                    <!-- Email Header -->
                    <tr>
                        <td style="padding: 32px 32px 16px 32px;">
                            <div style="display: inline-block; padding: 8px 16px; background-color: #f0f9ff; border-radius: 20px; margin-bottom: 16px;">
                                <span style="color: #0369a1; font-size: 14px; font-weight: bold;">NEW REGISTRATION</span>
                            </div>
                            <h2 style="color: #111827; margin: 0 0 16px 0; font-size: 24px; font-weight: bold;">
                                New School Registration
                            </h2>
                            <p style="color: #6b7280; margin: 0; font-size: 16px;">
                                A new school has registered on TermResult. Please review the details below.
                            </p>
                        </td>
                    </tr>

                    <!-- School Details Card -->
                    <tr>
                        <td style="padding: 0 32px 24px 32px;">
                            <div style="background-color: #f8fafc; border-radius: 8px; padding: 24px; border: 1px solid #e5e7eb;">
                                <h3 style="color: #111827; margin: 0 0 20px 0; font-size: 18px; font-weight: bold;">
                                    School Information
                                </h3>
                                
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td width="120" style="color: #6b7280; padding: 8px 0; font-size: 14px;"><strong>School Name:</strong></td>
                                        <td style="color: #111827; padding: 8px 0; font-size: 14px; font-weight: 500;">{{ $school->name }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; padding: 8px 0; font-size: 14px;"><strong>Subdomain:</strong></td>
                                        <td style="color: #111827; padding: 8px 0; font-size: 14px; font-weight: 500;">
                                            <span style="color: #059669; font-weight: bold;">{{ $school->subdomain }}.termresult.com</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; padding: 8px 0; font-size: 14px;"><strong>Email:</strong></td>
                                        <td style="color: #111827; padding: 8px 0; font-size: 14px; font-weight: 500;">{{ $school->contact_email }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; padding: 8px 0; font-size: 14px;"><strong>Phone:</strong></td>
                                        <td style="color: #111827; padding: 8px 0; font-size: 14px; font-weight: 500;">{{ $school->contact_phone ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6b7280; padding: 8px 0; font-size: 14px;"><strong>Address:</strong></td>
                                        <td style="color: #111827; padding: 8px 0; font-size: 14px; font-weight: 500;">{{ $school->address ?? 'N/A' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>

                    <!-- Action Buttons -->
                    <tr>
                        <td style="padding: 0 32px 32px 32px;">
                            <h3 style="color: #111827; margin: 0 0 16px 0; font-size: 18px; font-weight: bold;">
                                Action Required
                            </h3>
                            <p style="color: #6b7280; margin: 0 0 24px 0; font-size: 15px;">
                                Review the school information and approve or decline their registration.
                            </p>
                            
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <a href="{{ $acceptUrl }}" style="display: inline-block; padding: 12px 24px; background-color: #10b981; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);">
                                            ✓ Accept School
                                        </a>
                                    </td>
                                    <td width="16"></td>
                                    <td>
                                        <a href="{{ $declineUrl }}" style="display: inline-block; padding: 12px 24px; background-color: #f8fafc; color: #dc2626; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px; border: 1px solid #e5e7eb;">
                                            ✗ Decline School
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #9ca3af; font-size: 13px; margin: 16px 0 0 0;">
                                ⚠️ These action links expire automatically for security.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 32px; background-color: #f9fafb; border-top: 1px solid #e5e7eb;">
                            <p style="color: #6b7280; margin: 0 0 8px 0; font-size: 14px;">
                                <strong>TermResult Platform</strong><br>
                                School Management System
                            </p>
                            <p style="color: #9ca3af; margin: 0; font-size: 12px;">
                                This email was automatically generated. Please do not reply.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Company Footer -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #111827;">
        <tr>
            <td align="center" style="padding: 24px 20px;">
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
                                <a href="https://termresult.com/admin" style="color: #10b981; text-decoration: none;">Admin Panel</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>