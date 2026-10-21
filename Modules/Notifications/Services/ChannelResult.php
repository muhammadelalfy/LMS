<?php

namespace Modules\Notifications\Services;

/** What a channel reports after one send attempt. */
final class ChannelResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $providerId,
        public readonly ?string $reason,
        public readonly bool $retryable,
    ) {
    }

    public static function ok(?string $providerId = null): self
    {
        return new self(true, $providerId, null, false);
    }

    public static function failed(string $reason, bool $retryable = false): self
    {
        return new self(false, null, $reason, $retryable);
    }
}
