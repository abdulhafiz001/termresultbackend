<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolRegisterRequest;
use App\Mail\SchoolRegistrationReceived;
use App\Models\School;
use App\Models\SchoolApprovalToken;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SchoolRegistrationController extends Controller
{
    public function register(SchoolRegisterRequest $request)
    {
        $school = School::create([
            'name' => $request->input('name'),
            'subdomain' => $request->input('subdomain'),
            'contact_email' => $request->input('contact_email'),
            'contact_phone' => $request->input('contact_phone'),
            'address' => $request->input('address'),
            'status' => 'pending',
            'theme' => [
                'primary' => '#2563eb',
                'secondary' => '#0ea5e9',
                'logo_path' => null,
            ],
            'feature_toggles' => [
                'fees' => true,
                'complaints' => true,
                'materials' => true,
            ],
        ]);

        $plainToken = Str::random(48);

        SchoolApprovalToken::create([
            'school_id' => $school->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(3),
        ]);

        $acceptUrl = URL::temporarySignedRoute(
            'platform.approvals.accept',
            now()->addDays(3),
            ['token' => $plainToken]
        );

        $declineUrl = URL::temporarySignedRoute(
            'platform.approvals.declineForm',
            now()->addDays(3),
            ['token' => $plainToken]
        );

        $platformEmail = config('mail.platform_admin_email');
        if (! $platformEmail) {
            return response()->json([
                'message' => 'PLATFORM_ADMIN_EMAIL is not configured on the server.',
            ], 500);
        }

        Mail::to($platformEmail)->queue(new SchoolRegistrationReceived($school, $acceptUrl, $declineUrl));

        return response()->json([
            'message' => 'Registration received. You will receive an email after review.',
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'subdomain' => $school->subdomain,
                'status' => $school->status,
            ],
        ], 201);
    }
}


