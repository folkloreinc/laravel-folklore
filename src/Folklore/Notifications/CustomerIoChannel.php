<?php

namespace Folklore\Notifications;

use Folklore\Contracts\Services\CustomerIo;
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
        $notification = $notification->toCustomerIo($notifiable);
        $email =
            $notifiable->routeNotificationFor('customer_io', $notification) ??
            ($notifiable->routeNotificationFor('mail', $notification) ?? $notifiable->email());
        if ($notification instanceof CustomerIoMessage && !empty($email)) {
            resolve(CustomerIo::class)->sendEmail($notification, $email);
        } elseif ($notification instanceof CustomerIoWebhook) {
            resolve(CustomerIo::class)->triggerWebhook($notification->url, $notification->data);
        } elseif ($notification instanceof CustomerIoObject) {
            resolve(CustomerIo::class)->identifyObject($notification->toObject());
        }
    }
}
