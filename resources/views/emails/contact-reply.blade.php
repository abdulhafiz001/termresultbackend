<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reply from TermResult</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 30px; text-align: center;">
                            <img src="{{ asset('termresult-logo-no-bg.png') }}" alt="TermResult" style="max-width: 150px; height: auto; margin-bottom: 10px;" />
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;">Reply from TermResult</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Hello {{ $name }},
                            </p>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Thank you for contacting TermResult. We have received your message and are responding below:
                            </p>
                            
                            <div style="background-color: #f9fafb; border-left: 4px solid #4f46e5; padding: 20px; margin: 20px 0; border-radius: 4px;">
                                <h2 style="color: #4f46e5; margin: 0 0 15px 0; font-size: 18px;">{{ $subject }}</h2>
                                <div style="color: #333333; line-height: 1.6; white-space: pre-wrap;">{{ $messageBody }}</div>
                            </div>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 20px 0 0 0;">
                                If you have any further questions or concerns, please don't hesitate to reach out to us.
                            </p>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 20px 0 0 0;">
                                Best regards,<br>
                                <strong>The TermResult Team</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #666666; font-size: 12px; margin: 0;">
                                This is an automated reply from TermResult.<br>
                                Please do not reply directly to this email.
                            </p>
                            <p style="color: #999999; font-size: 11px; margin: 10px 0 0 0;">
                                © {{ date('Y') }} TermResult. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

