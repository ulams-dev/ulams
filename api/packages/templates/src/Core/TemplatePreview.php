<?php

namespace EscolaLms\Templates\Core;

use EscolaLms\Core\Models\User;

class TemplatePreview
{
    private array $data;
    private User $recipient;
    private bool $sent;

    public function __construct(User $recipient, array $data, bool $sent)
    {
        $this->data = $data;
        $this->recipient = $recipient;
        $this->sent = $sent;
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'recipient' => $this->recipient->toArray(),
            'sent' => $this->sent,
        ];
    }
}
