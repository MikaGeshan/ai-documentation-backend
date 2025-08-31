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
        return ['mail'];
    }

   public function toMail($notifiable)
    {
        $code = $this->otp;

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('Your OTP Code')
            ->line("Your OTP is: {$code}")
            ->line('It will expire in 15 minutes.');
    }
}

