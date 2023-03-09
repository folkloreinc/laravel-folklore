<?php

namespace Folklore\Notifications;

use Folklore\Contracts\Services\CustomerIo\Service as CustomerIo;
use Illuminate\Notifications\Notification;

class CustomerIoChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $email =
            $notifiable->routeNotificationFor('customer_io', $notification) ?? $notifiable->email();
        $message = $notification->toCustomerIo($notifiable);
        if (isset($message) && !empty($email)) {
            resolve(CustomerIo::class)->sendEmail($message, $email);
        }
    }
}
