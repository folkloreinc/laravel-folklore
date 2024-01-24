<?php

namespace Folklore\Notifications;

use Folklore\Contracts\Resources\User;
use Folklore\Contracts\Services\CustomerIo;
use Illuminate\Contracts\Support\Arrayable;

class CustomerIoMessage implements Arrayable
{
    public $id;

    public $cioId;

    public $email;

    public $user;

    public $subject;

    public $bcc;

    public $body;

    public $data = [];

    public function __construct($id = null)
    {
        $this->id = $id;
    }

    public static function fromName($name)
    {
        $message = resolve(CustomerIo::class)->findTransactionalMessageByName($name);
        return new self(isset($message) ? $message->id() : null);
    }

    public static function fromId($id)
    {
        $message = resolve(CustomerIo::class)->findTransactionalMessageById($id);
        return new self(isset($message) ? $message->id() : null);
    }

    public function subject(string $subject)
    {
        $this->subject = $subject;
        return $this;
    }

    public function bcc($bcc)
    {
        $this->bcc = is_array($bcc) ? implode(',', $bcc) : $bcc;
        return $this;
    }

    public function cioId(string $cioId)
    {
        $this->cioId = $cioId;
        return $this;
    }

    public function email(string $email)
    {
        $this->email = $email;
        return $this;
    }

    public function user(User $user)
    {
        $this->user = $user;
        return $this;
    }

    public function body(string $body)
    {
        $this->body = $body;
        return $this;
    }

    public function data(array $data)
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function toArray()
    {
        $data = [
            'identifiers' => [],
        ];
        if (!empty($this->id)) {
            $data['transactional_message_id'] = $this->id;
        }
        if (!empty($this->subject)) {
            $data['subject'] = $this->subject;
        }
        if (!empty($this->user)) {
            $data['identifiers']['email'] = $this->user->email();
        }
        if (!empty($this->email)) {
            $data['identifiers']['email'] = $this->email;
        }
        if (!empty($this->cioId)) {
            $data['identifiers']['cio_id'] = $this->cioId;
        }
        if (!empty($this->bcc)) {
            $data['bcc'] = $this->bcc;
        }
        if (!empty($this->body)) {
            $data['body'] = $this->body;
        }
        if (!empty($this->data)) {
            $data['message_data'] = $this->data;
        }

        return $data;
    }
}
