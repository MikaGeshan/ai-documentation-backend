<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Services\BrevoMailerService;

class BrevoOTPNotification extends Notification
{
    use Queueable;

    public string $token;

    /**
     * Take Token from array and convert it into string
     */
    public function __construct(array $token)
    {
        $this->token = $token['code'] ?? ''; 
    }

    public function via($notifiable)
    {
        return ['brevo'];
    }

    public function toBrevo($notifiable, array $token) // <-- token array comes here
{
    $this->token = $token['code'] ?? '';

    $mailer = app(BrevoMailerService::class);

    $email = method_exists($notifiable, 'routeNotificationForBrevo')
        ? $notifiable->routeNotificationForBrevo()
        : ($notifiable->email ?? null);

    $name = $notifiable->name ?? 'User';

    return $mailer->send(
        $email,
        $name,
        'AI Documentation Verify OTP Code',
        "<p>Hello,</p><p>Your OTP code is: <strong>{$this->token}</strong></p>"
    );
}
}
