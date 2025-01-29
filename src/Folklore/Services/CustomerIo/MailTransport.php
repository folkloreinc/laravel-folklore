<?php
namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo;
use MailchimpTransactional\ApiClient;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

class MailTransport extends AbstractTransport
{
    /**
     * Create a new Mailchimp transport instance.
     */
    public function __construct(protected CustomerIo $client, protected $config = [])
    {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $to = collect($email->getTo())
            ->map(function (Address $email) {
                return $email->getAddress();
            })
            ->first();
        $messageData = null;
        $transactionalMessageId = $email
            ->getHeaders()
            ->getHeaderBody('X-Metadata-transactional_message_id');
        $messageData = $email->getHeaders()->getHeaderBody('X-Metadata-message_data');
        $this->client->sendEmail(
            [
                'transactional_message_id' => $transactionalMessageId,
                'body' => $email->getHtmlBody(),
                'body_plain' => $email->getTextBody(),
                'subject' => $email->getSubject(),
                'from' => collect($email->getFrom())->first()->toString(),
                'to' => collect($email->getTo())
                    ->map(function (Address $email) {
                        return $email->getAddress();
                    })
                    ->join(','),
                'cc' => collect($email->getCc())
                    ->map(function (Address $email) {
                        return $email->getAddress();
                    })
                    ->join(','),
                'bcc' => collect($email->getBcc())
                    ->map(function (Address $email) {
                        return $email->getAddress();
                    })
                    ->join(','),
                'message_data' => $messageData ?? [],
            ],
            $to
        );
    }

    /**
     * Get the string representation of the transport.
     */
    public function __toString(): string
    {
        return 'customerio';
    }
}
