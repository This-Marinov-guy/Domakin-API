<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Notification extends Mailable
{
    use Queueable, SerializesModels;

    protected $subject;
    protected $templateUuid;
    protected $data;

    /**
     * Create a new message instance.
     *
     * @param string $subject
     * @param string $templateUuid
     * @param array $data
     */
    public function __construct($subject, $templateUuid, $data)
    {
        $this->subject = $subject;
        $this->templateUuid = $templateUuid;
        $this->data = $data;    
    }

    public function build()
    {
        return $this->subject($this->subject)
            ->view('emails.notification.' . $this->templateUuid)
            ->with($this->data);
    }
}
