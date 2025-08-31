<?php

namespace App\Services;

use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Configuration;
use Brevo\Client\Model\SendSmtpEmail;
use GuzzleHttp\Client;

class BrevoMailerService
{
    protected $apiInstance;

    public function __construct()
    {
        $config = Configuration::getDefaultConfiguration()->setApiKey(
            'api-key',
            env('BREVO_MAIL_API_KEY')
        );

        $this->apiInstance = new TransactionalEmailsApi(
            new Client(),
            $config
        );
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlContent)
    {
        $sendSmtpEmail = new SendSmtpEmail([
            'subject' => $subject,
            'sender' => ['name' => env('MAIL_FROM_NAME'), 'email' => env('MAIL_FROM_ADDRESS')],
            'to' => [[ 'email' => $toEmail, 'name' => $toName ]],
            'htmlContent' => $htmlContent,
        ]);

        return $this->apiInstance->sendTransacEmail($sendSmtpEmail);
    }
}
