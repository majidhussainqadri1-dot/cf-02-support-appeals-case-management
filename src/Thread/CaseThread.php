<?php

declare(strict_types=1);

namespace Sabri\CF02\Thread;

final class CaseThread
{
    /** @var array<string, CaseMessage> */
    private array $messagesByIdempotency = [];

    public function append(CaseMessage $message): bool
    {
        $key = $message->idempotencyKey();
        if (isset($this->messagesByIdempotency[$key])) {
            return false;
        }

        $this->messagesByIdempotency[$key] = $message;
        return true;
    }

    /** @return list<CaseMessage> */
    public function all(): array
    {
        return array_values($this->messagesByIdempotency);
    }

    /** @return list<CaseMessage> */
    public function requesterVisible(): array
    {
        return array_values(array_filter(
            $this->messagesByIdempotency,
            static fn (CaseMessage $message): bool => $message->visibility() === MessageVisibility::Requester
        ));
    }

    /** @return list<CaseMessage> */
    public function internalForRole(string $role): array
    {
        $allowedRestricted = in_array($role, ['sensitive_liaison', 'appeal_reviewer', 'auditor'], true);

        return array_values(array_filter(
            $this->messagesByIdempotency,
            static function (CaseMessage $message) use ($allowedRestricted): bool {
                if ($message->visibility() === MessageVisibility::Requester) {
                    return false;
                }

                return $message->visibility() === MessageVisibility::Internal || $allowedRestricted;
            }
        ));
    }
}
