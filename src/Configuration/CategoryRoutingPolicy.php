<?php

declare(strict_types=1);

namespace Sabri\CF02\Configuration;

use InvalidArgumentException;
use Sabri\CF02\Contracts\SupportContractCatalog;

/** Resolves every active or migratable category through the canonical taxonomy. */
final class CategoryRoutingPolicy
{
    public static function normalize(string $category): string
    {
        return SupportContractCatalog::normalizeCategory($category);
    }

    public static function queueFor(string $category): string
    {
        $normalized = self::normalize($category);
        $taxonomy = SupportTaxonomy::defaults();

        if (!isset($taxonomy[$normalized])) {
            throw new InvalidArgumentException('Support category has no canonical queue.');
        }

        return $taxonomy[$normalized]->queueKey();
    }
}
