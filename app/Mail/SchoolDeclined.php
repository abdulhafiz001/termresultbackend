<?php

namespace App\Mail;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class SchoolDeclined extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public School $school,
        public string $reason,
    ) {}

    public function build()
    {
        return $this
            ->subject('School Registration Declined')
            ->view('emails/school_declined');
    }
}


