<?php

namespace App\Mail;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SchoolPaymentReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public School $school;
    public object $payment;
    public ?object $student;
    public ?object $profile;
    public ?object $class;

    public function __construct(School $school, object $payment, ?object $student = null, ?object $profile = null, ?object $class = null)
    {
        $this->school = $school;
        $this->payment = $payment;
        $this->student = $student;
        $this->profile = $profile;
        $this->class = $class;
    }

    public function build()
    {
        $subject = 'Payment received - ' . ($this->school->name ?? 'School');

        return $this->subject($subject)
            ->view('emails.school_payment_received')
            ->with([
                'school' => $this->school,
                'payment' => $this->payment,
                'student' => $this->student,
                'profile' => $this->profile,
                'class' => $this->class,
            ]);
    }
}


