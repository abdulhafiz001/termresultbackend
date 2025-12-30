<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ContactMessagesController extends Controller
{
    public function index()
    {
        $messages = DB::table('contact_messages')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $messages,
        ]);
    }

    public function reply(Request $request, int $id)
    {
        $contact = DB::table('contact_messages')->where('id', $id)->first();
        if (!$contact) {
            return response()->json(['message' => 'Message not found.'], 404);
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        // Send reply email
        try {
            Mail::send('emails.contact-reply', [
                'name' => $contact->name,
                'subject' => $data['subject'],
                // NOTE: In Laravel mail views, $message is reserved (Illuminate\Mail\Message).
                'messageBody' => $data['message'],
            ], function ($mail) use ($contact, $data) {
                $mail->to($contact->email)
                    ->subject($data['subject']);
            });
        } catch (\Exception $e) {
            \Log::error('Failed to send reply email: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to send reply email.'], 500);
        }

        // Mark as replied
        DB::table('contact_messages')
            ->where('id', $id)
            ->update([
                'replied' => true,
                'replied_at' => now(),
                'updated_at' => now(),
            ]);

        // Log activity (best-effort)
        try {
            /** @var \App\Models\PlatformAdmin $actor */
            $actor = $request->user();
            DB::table('platform_admin_activities')->insert([
                'platform_admin_id' => $actor->id,
                'action' => 'contact_message_replied',
                'subject_id' => $contact->id,
                'subject_type' => 'contact_message',
                'metadata' => json_encode([
                    'to' => $contact->email,
                    'subject' => $data['subject'],
                ]),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json([
            'message' => 'Reply sent successfully.',
        ]);
    }
}

