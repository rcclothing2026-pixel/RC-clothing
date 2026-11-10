<?php

namespace App\Services\StockKeeping;

use RuntimeException;

/** A refused or failed StoqS API call: HTTP status, StoqS error code, decoded body. */
class StoqsApiError extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $body = [],
    ) {
        parent::__construct($message !== '' ? $message : "StoqS HTTP $status");
    }

    /** The key lacks the scope this call needs (403 forbidden_scope). */
    public function missingScope(): bool
    {
        return $this->status === 403 && in_array($this->errorCode, ['forbidden_scope', ''], true);
    }
}
