<?php

namespace App\Mail;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class SchoolRegistrationReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public School $school,
        public string $acceptUrl,
        public string $declineUrl,
    ) {}

    public function build()
    {
        return $this
            ->subject('New School Registration: '.$this->school->name)
            ->view('emails/school_registration_received');
    }
}


