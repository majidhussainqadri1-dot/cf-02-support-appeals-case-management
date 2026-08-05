<?php

declare(strict_types=1);

namespace Sabri\CF02\Integration;

use DateTimeImmutable;
use InvalidArgumentException;

final class ImplementationReconciler
{
    /** @return array{status:string,reasons:list<string>,checked_at:string} */
    public function reconcile(
        NativeOwnerCommand $command,
        string $observedOwner,
        string $observedAction,
        string $observedObjectReference,
        int $observedVersion,
        string $observedOutcomeReference,
        DateTimeImmutable $checkedAt
    ): array {
        if (trim($observedOutcomeReference) === '' || $observedVersion < 1) {
            throw new InvalidArgumentException('Observed native outcome is incomplete.');
        }
        $reasons = [];
        if ($command->state() !== CommandState::Succeeded) {
            $reasons[] = 'Command has not reached a succeeded state.';
        }
        if (!hash_equals($command->nativeOwner(), $observedOwner)) {
            $reasons[] = 'Observed native owner does not match the command contract.';
        }
        if (!hash_equals($command->action(), $observedAction)) {
            $reasons[] = 'Observed native action does not match the requested action.';
        }
        if (!hash_equals($command->objectReference(), $observedObjectReference)) {
            $reasons[] = 'Observed native object does not match the command target.';
        }
        if ($observedVersion < $command->expectedNativeVersion()) {
            $reasons[] = 'Observed native version is stale.';
        }
        if ($command->nativeOutcomeReference() === null || !hash_equals($command->nativeOutcomeReference(), $observedOutcomeReference)) {
            $reasons[] = 'Observed outcome reference does not match the acknowledged command result.';
        }

        return [
            'status' => $reasons === [] ? 'reconciled' : 'drift',
            'reasons' => $reasons === [] ? ['Native implementation matches the command contract.'] : $reasons,
            'checked_at' => $checkedAt->format(DATE_ATOM),
        ];
    }
}
