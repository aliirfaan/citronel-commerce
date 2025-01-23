<?php

namespace aliirfaan\CitronelCommerce\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class SupportFulfillmentFailure extends Mailable
{
    use Queueable, SerializesModels;

    public $mailVars;

    /**
     * Create a new message instance.
     */
    public function __construct($mailVars)
    {
        $this->mailVars = $mailVars;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = config('app.name') . ' - Fulfillment Issue order #' . $this->mailVars['payment']->gateway_merchant_transaction_no;

        return new Envelope(
            subject: $subject,
            from: new Address(config('mail.from.address'), config('mail.from.name')),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order.unfulfilled',
            with: [
                'mailVars' => $this->mailVars,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
