<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BrevoOTPNotification extends Notification
{
    use Queueable;

    public string $token;

    /**
     * @param  string|array
     */
    public function __construct($token)
    {
        if (is_array($token) && isset($token['token'])) {
            $this->token = $token['token'];
        } elseif (is_string($token)) {
            $this->token = $token;
        } else {
            // fallback untuk debugging
            $this->token = json_encode($token);
        }
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your OTP Code')
            ->greeting('Hello!')
            ->line("Your OTP code is: **{$this->token}**")
            ->line('This code will expire in 15 minutes.');
    }
}


