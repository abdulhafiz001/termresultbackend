<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactReply extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $subjectLine,
        public string $messageBody,
    ) {}

    public function build()
    {
        return $this
            ->subject($this->subjectLine)
            ->view('emails.contact-reply')
            ->with([
                'name' => $this->name,
                'subject' => $this->subjectLine,
                // NOTE: In Laravel mail views, $message is reserved (Illuminate\Mail\Message).
                'messageBody' => $this->messageBody,
            ]);
    }
}


