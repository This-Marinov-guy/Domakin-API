<?php

namespace App\Mail;

use App\Constants\Emails;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class Notification extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $templateUuid;
    public $data;

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
            ->view('notifications.' . $this->templateUuid)
            ->with($this->data);
    }

    public function sendNotification()
    {
        if (env('APP_ENV', 'prod') === 'dev') return;
        if (config('mail.notifications_enabled') === false) return;

        return Mail::to(Emails::SYSTEM['internal_receiver'])->send($this);
    }
}
