<?php

namespace App\Mail\Network;

readonly class HttpsResult implements \JsonSerializable
{
    public function __construct(public bool $success, public string $body = '', public int $status = 0, public ?string $error = null, public ?string $ip = null, public ?string $contentType = null) {}

    public function jsonSerialize(): array
    {
        return ['success' => $this->success, 'status' => $this->status, 'error' => $this->error, 'ip' => $this->ip, 'content_type' => $this->contentType];
    }
}
