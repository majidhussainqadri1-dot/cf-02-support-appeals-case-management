<?php

declare(strict_types=1);

namespace Sabri\CF02\Knowledge;

use DateTimeImmutable;
use InvalidArgumentException;

final class KnowledgeSuggestionService
{
    /** @param list<KnowledgeArticle> $articles @return list<array<string, string>> */
    public function suggest(string $category, array $articles, DateTimeImmutable $at, int $limit = 3): array
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $category) !== 1 || $limit < 1 || $limit > 10) {
            throw new InvalidArgumentException('Invalid knowledge suggestion request.');
        }
        $matches = [];
        foreach ($articles as $article) {
            if (!$article instanceof KnowledgeArticle) {
                throw new InvalidArgumentException('Knowledge suggestion corpus is malformed.');
            }
            if (!$article->supportsCategory($category) || !$article->isUsable($at)) {
                continue;
            }
            $matches[] = [
                'article_id' => $article->articleId(),
                'title' => $article->title(),
                'owner_reference' => $article->ownerReference(),
                'source_reference' => $article->sourceReference(),
                'version' => $article->version(),
                'editable_draft' => $article->safeDraftText(),
                'execution_boundary' => 'Suggestion only; cannot execute account, refund, moderation, clinical or safety action.',
            ];
        }
        usort($matches, static fn (array $a, array $b): int => strcmp($a['article_id'], $b['article_id']));
        return array_slice($matches, 0, $limit);
    }
}
