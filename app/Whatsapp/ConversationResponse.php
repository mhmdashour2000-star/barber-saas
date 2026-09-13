<?php

namespace App\Whatsapp;

use JsonSerializable;

final readonly class ConversationResponse implements JsonSerializable
{
    public function __construct(
        public string $type,
        public string $text,
        public array $choices = [],
        public array $data = [],
    ) {}

    public function jsonSerialize(): array
    {
        return ['type' => $this->type, 'text' => $this->text, 'choices' => $this->choices, 'data' => $this->data];
    }

    public static function fromArray(array $value): self
    {
        return new self($value['type'], $value['text'], $value['choices'] ?? [], $value['data'] ?? []);
    }
}
