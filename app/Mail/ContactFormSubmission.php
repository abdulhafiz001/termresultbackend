<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactFormSubmission extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $subjectLine,
        public string $messageBody,
        public int $contactId,
    ) {}

    public function build()
    {
        return $this
            ->subject('New Contact Form Submission: ' . $this->subjectLine)
            ->replyTo($this->email, $this->name)
            ->view('emails.contact')
            ->with([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'subject' => $this->subjectLine,
                // NOTE: In Laravel mail views, $message is reserved (Illuminate\Mail\Message).
                'messageBody' => $this->messageBody,
                'contactId' => $this->contactId,
            ]);
    }
}


