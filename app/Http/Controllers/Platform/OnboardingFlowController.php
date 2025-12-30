<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OnboardingFlowController extends Controller
{
    public function download(Request $request)
    {
        $pdf = Pdf::loadView('platform.onboarding-flow', [
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('termresult-school-onboarding-flow.pdf');
    }
}


