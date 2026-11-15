<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

/**
 * @deprecated The administration script is now an external, CSP-compatible
 * asset at assets/js/cf02-admin.js. Kept for compatibility with legacy code.
 */
final class CompleteAdminScript
{
    public static function render(): string
    {
        return '';
    }
}
