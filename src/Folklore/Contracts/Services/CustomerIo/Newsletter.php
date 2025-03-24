<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Carbon\Carbon;
use Folklore\Contracts\Resources\Resource;
use Illuminate\Support\Collection;

interface Newsletter extends Resource
{
    public function name(): string;

    public function type(): string;

    public function medium(): string;

    public function content(): NewsletterContent;

    public function tags(): Collection;

    public function sentAt(): ?Carbon;

    public function createdAt(): ?Carbon;

    public function updatedAt(): ?Carbon;
}
