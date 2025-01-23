<?php

namespace aliirfaan\CitronelCommerce\Notifications\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use aliirfaan\CitronelCommerce\Mail\Order\ActorFulfillmentFailure as MailActorFulfillmentFailure;

class ActorFulfillmentFailure extends Notification
{
    use Queueable;

    protected $notificationVars;

    /**
     * Create a new notification instance.
     */
    public function __construct($notificationVars)
    {
        $this->notificationVars = $notificationVars;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): Mailable
    {
        return (new MailActorFulfillmentFailure($this->notificationVars))
                    ->to($notifiable->email);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
