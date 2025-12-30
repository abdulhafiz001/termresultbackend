<?php

namespace App\Mail;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SchoolApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{admin:string,teacher:string,student:string,landing?:string}  $links
     */
    public function __construct(
        public School $school,
        public string $adminUsername,
        public string $adminPassword,
        public array $links,
    ) {}

    public function build()
    {
        // Ensure School model is using the central connection
        // SerializesModels will re-fetch the model, and since School has
        // protected $connection = 'mysql', it should use that connection
        // But we refresh it here to be safe
        if ($this->school->getConnectionName() !== 'mysql') {
            $this->school = School::on('mysql')->findOrFail($this->school->id);
        }

        // Log email sending for debugging
        Log::info('Building SchoolApproved email', [
            'school_id' => $this->school->id,
            'school_name' => $this->school->name,
            'email' => $this->school->contact_email,
            'connection' => $this->school->getConnectionName(),
        ]);

        return $this
            ->subject('Your TermResult School Portal is Ready')
            ->view('emails/school_approved');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SchoolApproved email job failed', [
            'school_id' => $this->school->id ?? null,
            'email' => $this->school->contact_email ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}


