<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;

class BrevoMailChannel
{
    public function send($notifiable, Notification $notification)
    {
        if (! method_exists($notification, 'toBrevo')) {
            return;
        }

        return $notification->toBrevo($notifiable);
    }
}
