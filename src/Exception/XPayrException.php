<?php

declare(strict_types=1);

namespace XPayr\Exception;

use RuntimeException;
use Throwable;

final class XPayrException extends RuntimeException
{
    /** @param array<string, mixed>|null $details */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly string $errorCode = 'api_error',
        public readonly ?array $details = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
