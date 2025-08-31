<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Services\BrevoMailerService;

class BrevoOTPNotification extends Notification
{
    use Queueable;

    public string $token;

    // Accept the OTP array directly from LaravelOtp
    public function __construct(array $token)
    {
        $this->token = $token['code'] ?? '';
    }

    public function via($notifiable)
    {
        return ['brevo'];
    }

    public function toBrevo($notifiable)
    {
        $mailer = app(BrevoMailerService::class);

        $email = $notifiable->routes['brevo'] ?? null;

        if (!$email) {
            throw new \Exception('Email not found for Brevo notification.');
        }

        return $mailer->send(
            $email,
            'User', // no need to send name
            'AI Documentation Verify OTP Code',
            "<p>Hello,</p><p>Your OTP code is: <strong>{$this->token}</strong></p>"
        );
    }
}
