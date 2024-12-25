<?php

namespace App\Mail;

use App\Constants\Emails;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;

class MailTrap extends MailtrapEmail
{

    protected $email;
    protected $templateUuid;
    protected $data;

    /**
     * Create a new message instance.
     *
     * @param string $email
     * @param string $templateUuid
     * @param array $data
     */
    public function __construct($email, $templateUuid, $data)
    {
        $this->email = $email;
        $this->templateUuid = $templateUuid;
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(new Address(Emails::MAILTRAP['email'], Emails::MAILTRAP['name']))
            ->to($this->email)
            ->templateUuid($this->templateUuid)
            ->templateVariables($this->data);
    }

    public function send()
    {
        $client = new MailtrapClient(env('MAILTRAP_API_KEY'));
        $client->sendEmail($this->build());
    }
}
