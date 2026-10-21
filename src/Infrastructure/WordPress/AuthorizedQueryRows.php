<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use LogicException;

/** Distinguishes an authorized empty query from a failed database query. */
final class AuthorizedQueryRows
{
    /** @return list<array<string,mixed>> */
    public static function fromDatabase(mixed $value): array
    {
        if (!is_array($value)) {
            throw new LogicException('The authorized case/appeal database query is unavailable.');
        }
        foreach ($value as $row) {
            if (!is_array($row)) {
                throw new LogicException('The authorized query returned malformed rows.');
            }
        }
        return array_values($value);
    }
}
