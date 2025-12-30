<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Update - TermResult</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f9fafb; font-family: Arial, sans-serif; line-height: 1.6; color: #374151;">
    <!-- Header with Logo -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #111827;">
        <tr>
            <td align="center" style="padding: 32px 0;">
                <table cellpadding="0" cellspacing="0" style="max-width: 600px;">
                    <tr>
                        <td align="center">
                            <!-- Logo -->
                            <img src="https://i.ibb.co/xtnR63Qs/termresult-logo.png" alt="TermResult" style="height: 50px; display: block; margin: 0 auto 12px;">
                            <p style="color: #9ca3af; margin: 0; font-size: 14px; letter-spacing: 0.5px;">
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
                    <!-- Status Header -->
                    <tr>
                        <td style="padding: 32px 32px 16px 32px;">
                            <div style="display: flex; align-items: center; margin-bottom: 20px;">
                                <div style="background-color: #fef2f2; border-radius: 50%; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; margin-right: 16px;">
                                    <span style="color: #dc2626; font-size: 28px;">✗</span>
                                </div>
                                <div>
                                    <div style="display: inline-block; padding: 6px 12px; background-color: #fef2f2; border-radius: 16px; margin-bottom: 8px;">
                                        <span style="color: #991b1b; font-size: 13px; font-weight: bold;">REGISTRATION UPDATE</span>
                                    </div>
                                    <h2 style="color: #111827; margin: 0; font-size: 24px; font-weight: bold;">
                                        School Registration Status
                                    </h2>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Main Message -->
                    <tr>
                        <td style="padding: 0 32px 24px 32px;">
                            <p style="color: #111827; margin: 0 0 20px 0; font-size: 18px; line-height: 1.8;">
                                Hello <strong style="color: #111827;">{{ $school->name }}</strong>,
                            </p>
                            
                            <div style="background-color: #f8fafc; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                                <p style="color: #374151; margin: 0; font-size: 16px; line-height: 1.7;">
                                    We've reviewed your registration request for TermResult, and unfortunately, we're unable to approve it at this time.
                                </p>
                            </div>

                            <!-- Reason Section -->
                            <div style="background-color: #fef2f2; border-radius: 8px; border: 1px solid #fecaca; padding: 24px; margin-bottom: 32px;">
                                <h3 style="color: #991b1b; margin: 0 0 16px 0; font-size: 18px; font-weight: bold; display: flex; align-items: center;">
                                    <span style="margin-right: 10px;">📋</span>
                                    Reason for Decline
                                </h3>
                                
                                <div style="background-color: white; border-radius: 6px; padding: 20px; border-left: 4px solid #dc2626;">
                                    <p style="color: #374151; margin: 0; font-size: 15px; line-height: 1.7; font-style: italic;">
                                        "{{ $reason }}"
                                    </p>
                                </div>
                            </div>

                            <!-- Next Steps -->
                            <div style="background-color: #eff6ff; border-radius: 8px; padding: 24px; border: 1px solid #dbeafe; margin-bottom: 24px;">
                                <h3 style="color: #1e40af; margin: 0 0 16px 0; font-size: 18px; font-weight: bold; display: flex; align-items: center;">
                                    <span style="margin-right: 10px;">🔄</span>
                                    Next Steps
                                </h3>
                                
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="padding-bottom: 16px;">
                                            <div style="display: flex; align-items: flex-start;">
                                                <div style="background-color: #dbeafe; color: #1e40af; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0; font-weight: bold; font-size: 14px;">1</div>
                                                <div>
                                                    <p style="color: #374151; margin: 0; font-size: 15px;">
                                                        <strong>Review the reason above</strong> - Understand why your registration was declined
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 16px;">
                                            <div style="display: flex; align-items: flex-start;">
                                                <div style="background-color: #dbeafe; color: #1e40af; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0; font-weight: bold; font-size: 14px;">2</div>
                                                <div>
                                                    <p style="color: #374151; margin: 0; font-size: 15px;">
                                                        <strong>Correct the issue</strong> - Address the concern mentioned in the reason
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: flex-start;">
                                                <div style="background-color: #dbeafe; color: #1e40af; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0; font-weight: bold; font-size: 14px;">3</div>
                                                <div>
                                                    <p style="color: #374151; margin: 0; font-size: 15px;">
                                                        <strong>Re-register or contact support</strong> - Submit a new application or get assistance
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Support CTAs -->
                            <div style="background-color: #f8fafc; border-radius: 8px; padding: 24px; border: 1px solid #e5e7eb;">
                                <h3 style="color: #111827; margin: 0 0 16px 0; font-size: 18px; font-weight: bold;">
                                    Need Assistance?
                                </h3>
                                
                                <p style="color: #6b7280; margin: 0 0 20px 0; font-size: 15px;">
                                    We're here to help you through the registration process. Choose an option below:
                                </p>
                                
                                <table cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td>
                                            <a href="https://termresult.com/register" style="display: inline-block; padding: 12px 24px; background-color: #111827; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px;">
                                                ↻ Re-register Now
                                            </a>
                                        </td>
                                        <td width="16"></td>
                                        <td>
                                            <a href="https://termresult.com/support" style="display: inline-block; padding: 12px 24px; background-color: #f8fafc; color: #111827; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px; border: 1px solid #e5e7eb;">
                                                💬 Contact Support
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>

                    <!-- Additional Info -->
                    <tr>
                        <td style="padding: 0 32px 32px 32px;">
                            <div style="background-color: #fefce8; padding: 16px; border-radius: 6px; border: 1px solid #fef08a;">
                                <p style="color: #854d0e; margin: 0; font-size: 14px; display: flex; align-items: flex-start;">
                                    <span style="margin-right: 8px; flex-shrink: 0;">💡</span>
                                    <span><strong>Note:</strong> Most registration issues can be resolved easily. We encourage you to address the concern and submit a new application. Our team is available to help clarify any requirements.</span>
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 32px; background-color: #f9fafb; border-top: 1px solid #e5e7eb;">
                            <table width="100%">
                                <tr>
                                    <td>
                                        <p style="color: #6b7280; margin: 0 0 8px 0; font-size: 14px;">
                                            <strong>TermResult Support Team</strong>
                                        </p>
                                        <p style="color: #9ca3af; margin: 0; font-size: 13px;">
                                            This decision is based on our current registration requirements and is not a reflection of your institution's quality.
                                        </p>
                                    </td>
                                </tr>
                            </table>
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
                                <a href="https://termresult.com" style="color:rgb(16, 47, 185); text-decoration: none;">Website</a>
                                <span style="color: #4b5563;"> • </span>
                                <a href="https://termresult.com/requirements" style="color:rgb(35, 37, 167); text-decoration: none;">Registration Requirements</a>
                                <span style="color: #4b5563;"> • </span>
                                <a href="https://termresult.com/faq" style="color:rgb(16, 47, 185); text-decoration: none;">FAQ</a>
                            </p>
                            <p style="color: #4b5563; margin: 16px 0 0 0; font-size: 12px;">
                                This is an automated message. Please contact support for inquiries.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>