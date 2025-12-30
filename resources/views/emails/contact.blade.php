<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Form Submission</title>
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
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;">New Contact Form Submission</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                You have received a new message from the TermResult contact form.
                            </p>
                            
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; padding: 20px; margin-bottom: 20px;">
                                <tr>
                                    <td style="padding-bottom: 10px;">
                                        <strong style="color: #4f46e5;">Name:</strong>
                                        <span style="color: #333333; margin-left: 10px;">{{ $name }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 10px;">
                                        <strong style="color: #4f46e5;">Email:</strong>
                                        <span style="color: #333333; margin-left: 10px;"><a href="mailto:{{ $email }}" style="color: #4f46e5; text-decoration: none;">{{ $email }}</a></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 10px;">
                                        <strong style="color: #4f46e5;">Phone:</strong>
                                        <span style="color: #333333; margin-left: 10px;">{{ $phone }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 10px;">
                                        <strong style="color: #4f46e5;">Subject:</strong>
                                        <span style="color: #333333; margin-left: 10px;">{{ $subject }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 10px;">
                                        <strong style="color: #4f46e5; display: block; margin-bottom: 8px;">Message:</strong>
                                        <div style="color: #333333; line-height: 1.6; white-space: pre-wrap;">{{ $messageBody }}</div>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #666666; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;">
                                <strong>Message ID:</strong> #{{ $contactId }}<br>
                                <strong>Submitted:</strong> {{ now()->format('F j, Y \a\t g:i A') }}
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #666666; font-size: 12px; margin: 0;">
                                This email was sent from the TermResult contact form.<br>
                                Please reply directly to <a href="mailto:{{ $email }}" style="color: #4f46e5; text-decoration: none;">{{ $email }}</a> to respond to this inquiry.
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

