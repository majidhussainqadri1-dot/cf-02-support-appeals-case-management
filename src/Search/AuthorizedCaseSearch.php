<?php

declare(strict_types=1);

namespace Sabri\CF02\Search;

use InvalidArgumentException;
use Sabri\CF02\Authorization\AccessContext;

final class AuthorizedCaseSearch
{
    /**
     * @param list<CaseSearchDocument> $documents
     * @return array{results:list<array<string, scalar>>,next_cursor:?string}
     */
    public function search(
        AccessContext $context,
        array $documents,
        string $query,
        ?string $queueKey = null,
        ?string $category = null,
        int $limit = 20,
        ?string $cursor = null
    ): array {
        if (!$context->hasCapability('case.search')) {
            throw new InvalidArgumentException('Search capability is required.');
        }
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Search page size must be between 1 and 100.');
        }
        $query = trim(strtolower($query));
        if ($query === '' || strlen($query) > 100) {
            throw new InvalidArgumentException('Search query must be between 1 and 100 bytes.');
        }
        if ($queueKey !== null && preg_match('/^[a-z][a-z0-9_]*$/', $queueKey) !== 1) {
            throw new InvalidArgumentException('Invalid queue search filter.');
        }
        if ($category !== null && preg_match('/^[a-z][a-z0-9_.-]*$/', $category) !== 1) {
            throw new InvalidArgumentException('Invalid category search filter.');
        }
        $offset = 0;
        if ($cursor !== null) {
            $padded = strtr($cursor, '-_', '+/');
            $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
            $decoded = base64_decode($padded, true);
            if ($decoded === false || preg_match('/^offset:(\d+)$/', $decoded, $matches) !== 1) {
                throw new InvalidArgumentException('Invalid bounded search cursor.');
            }
            $offset = (int) $matches[1];
        }

        $visible = [];
        foreach ($documents as $document) {
            if (!$document instanceof CaseSearchDocument) {
                throw new InvalidArgumentException('Search documents must be case search projections.');
            }
            if (!$document->authorizedFor($context->actorReference())
                && !$context->isAssignedTo($document->caseId())
                && !$context->isAssignedQueue($document->queueKey())) {
                continue;
            }
            if ($queueKey !== null && $document->queueKey() !== $queueKey) {
                continue;
            }
            if ($category !== null && $document->category() !== $category) {
                continue;
            }
            $haystack = strtolower(implode(' ', [
                $document->caseId()->value(),
                $document->safeSubject(),
                $document->category(),
                $document->state(),
                $document->priority(),
            ]));
            if (!str_contains($haystack, $query)) {
                continue;
            }
            $visible[] = $document;
        }

        usort($visible, static function (CaseSearchDocument $left, CaseSearchDocument $right): int {
            $updated = $right->updatedAt() <=> $left->updatedAt();
            return $updated !== 0 ? $updated : strcmp($left->caseId()->value(), $right->caseId()->value());
        });

        $slice = array_slice($visible, $offset, $limit);
        $results = array_map(static fn (CaseSearchDocument $document): array => [
            'case_id' => $document->caseId()->value(),
            'queue' => $document->queueKey(),
            'category' => $document->category(),
            'priority' => $document->priority(),
            'state' => $document->state(),
            'locale' => $document->locale(),
            'subject' => $document->safeSubject(),
            'record_version' => $document->recordVersion(),
            'updated_at' => $document->updatedAt()->format(DATE_ATOM),
        ], $slice);

        $nextOffset = $offset + count($slice);
        $nextCursor = $nextOffset < count($visible)
            ? rtrim(strtr(base64_encode('offset:' . $nextOffset), '+/', '-_'), '=')
            : null;

        return ['results' => $results, 'next_cursor' => $nextCursor];
    }
}
