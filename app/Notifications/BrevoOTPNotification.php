<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Services\BrevoMailerService;

class BrevoOTPNotification extends Notification
{
    use Queueable;

    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['brevo'];
    }

    public function toBrevo($notifiable)
    {
        $mailer = app(BrevoMailerService::class);

        return $mailer->send(
            $notifiable->email,
            $notifiable->name ?? 'User',
            'AI Documentation Verify OTP Code',
            "<p>Hello,</p><p>Your OTP code is: <strong>{$this->token}</strong></p>"
        );
    }
}
