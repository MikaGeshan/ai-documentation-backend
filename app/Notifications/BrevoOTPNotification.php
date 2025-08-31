<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class BrevoOTPNotification extends Notification
{
    use Queueable;

    protected $otp;

    public function __construct(array $otp)
    {
        $this->otp = $otp;
    }

    public function via($notifiable)
    {
        return ['brevo'];
    }

    public function toBrevo($notifiable)
    {
        $code = $this->otp['token'];

        return Http::withHeaders([
            'api-key' => env('BREVO_API_KEY'),
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender' => [
                'name'  => config('mail.from.name'),
                'email' => config('mail.from.address'),
            ],
            'to' => [
                ['email' => $notifiable->email],
            ],
            'subject' => 'Your OTP Code',
            'htmlContent' => "<p>Your OTP is <strong>{$code}</strong></p>",
        ]);
    }
}

