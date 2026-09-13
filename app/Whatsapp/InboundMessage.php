<?php

namespace App\Whatsapp;

use App\Models\Company;
use App\Support\PhoneHelper;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Trusted adapter boundary, NOT a public request DTO or a Meta payload parser. */
final readonly class InboundMessage
{
    public string $customerPhone;

    public function __construct(
        public string $provider,
        public string $externalMessageId,
        public Company $company,
        string $customerPhone,
        public string $messageType,
        public string $payload,
        public CarbonImmutable $receivedAt,
    ) {
        $this->customerPhone = PhoneHelper::normalize($customerPhone) ?? '';
        if (!preg_match('/^[a-z0-9_-]{1,32}$/D', $provider)
            || trim($externalMessageId) === '' || trim($externalMessageId) !== $externalMessageId || strlen($externalMessageId) > 191
            || !preg_match('/^\+[1-9][0-9]{7,14}$/D', $this->customerPhone)
            || !in_array($messageType, ['text', 'button', 'list'], true)
            || mb_strlen($payload) > 2000 || !$company->exists) {
            throw new InvalidArgumentException('Invalid normalized inbound message.');
        }
    }

    public function fingerprint(): string
    {
        return hash_hmac('sha256', json_encode([$this->customerPhone, $this->messageType, $this->payload], JSON_THROW_ON_ERROR),
            (string) config('app.key'));
    }
}
