<?php

declare(strict_types=1);

namespace Sabri\CF02\Knowledge;

use DateTimeImmutable;
use InvalidArgumentException;

final class KnowledgeArticle
{
    /** @param list<string> $categories */
    public function __construct(
        private readonly string $articleId,
        private readonly string $title,
        private readonly string $ownerReference,
        private readonly string $sourceReference,
        private readonly string $version,
        private readonly array $categories,
        private readonly string $safeDraftText,
        private readonly DateTimeImmutable $reviewedAt,
        private readonly DateTimeImmutable $expiresAt,
        private readonly bool $approved,
        private readonly bool $safeForAutomation
    ) {
        if (preg_match('/^KB-[A-Z0-9-]{3,40}$/', $articleId) !== 1) {
            throw new InvalidArgumentException('Invalid knowledge article ID.');
        }
        foreach ([$title, $ownerReference, $sourceReference, $version, $safeDraftText] as $required) {
            if (trim($required) === '') {
                throw new InvalidArgumentException('Knowledge article fields are required.');
            }
        }
        if ($categories === []) {
            throw new InvalidArgumentException('Knowledge article requires at least one category.');
        }
        foreach ($categories as $category) {
            if (!is_string($category) || preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $category) !== 1) {
                throw new InvalidArgumentException('Invalid knowledge article category.');
            }
        }
        if ($expiresAt <= $reviewedAt || $expiresAt > $reviewedAt->modify('+2 years')) {
            throw new InvalidArgumentException('Knowledge article review expiry is invalid.');
        }
    }

    public function articleId(): string { return $this->articleId; }
    public function title(): string { return $this->title; }
    public function ownerReference(): string { return $this->ownerReference; }
    public function sourceReference(): string { return $this->sourceReference; }
    public function version(): string { return $this->version; }
    public function safeDraftText(): string { return $this->safeDraftText; }
    public function supportsCategory(string $category): bool { return in_array($category, $this->categories, true); }
    public function isUsable(DateTimeImmutable $at): bool { return $this->approved && $this->safeForAutomation && $at < $this->expiresAt; }
}
