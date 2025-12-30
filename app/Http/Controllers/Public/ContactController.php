<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        // Rate limiting: 2 messages per minute, then 10 minute ban
        $key = 'contact:' . ($request->ip() ?? 'unknown');
        
        if (RateLimiter::tooManyAttempts($key, 2)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Too many messages sent. Please wait ' . ceil($seconds / 60) . ' minutes before sending another message.',
            ], 429);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        // Basic sanitization (server-side)
        $data['name'] = trim(strip_tags((string) $data['name']));
        $data['email'] = trim((string) $data['email']);
        $data['phone'] = trim(strip_tags((string) $data['phone']));
        $data['subject'] = trim(strip_tags((string) $data['subject']));
        $data['message'] = trim((string) $data['message']);

        // Store in database
        $contactId = DB::table('contact_messages')->insertGetId([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Rate limit: 2 attempts per minute, then 10 minute ban
        RateLimiter::hit($key, 600); // 10 minutes = 600 seconds

        // Send email
        try {
            $toEmail = env('CONTACT_RECEIVER_EMAIL', env('MAIL_FROM_ADDRESS', 'support@termresult.com'));
            Mail::send('emails.contact', [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'subject' => $data['subject'],
                // NOTE: In Laravel mail views, $message is reserved (Illuminate\Mail\Message).
                // Use a different variable name for the actual body.
                'messageBody' => $data['message'],
                'contactId' => $contactId,
            ], function ($message) use ($toEmail, $data) {
                $message->to($toEmail)
                    ->subject('New Contact Form Submission: ' . $data['subject']);
            });
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::error('Failed to send contact email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Thank you for contacting us! We will get back to you soon.',
        ], 201);
    }
}

