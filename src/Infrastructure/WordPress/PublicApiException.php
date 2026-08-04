<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use RuntimeException;
use Throwable;

final class PublicApiException extends RuntimeException
{
    public function __construct(
        private readonly string $publicCode,
        private readonly string $publicMessage,
        private readonly int $httpStatus,
        ?Throwable $previous = null
    ) { parent::__construct($publicMessage, 0, $previous); }

    public function publicCode(): string { return $this->publicCode; }
    public function publicMessage(): string { return $this->publicMessage; }
    public function httpStatus(): int { return $this->httpStatus; }
}
