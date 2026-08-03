<?php

declare(strict_types=1);

namespace Sabri\CF02\Portal;

use Sabri\CF02\Domain\CaseWorkspace;
use Sabri\CF02\Thread\CaseMessage;

final class UserCaseProjection
{
    /** @return array<string, mixed> */
    public static function fromWorkspace(CaseWorkspace $case): array
    {
        $messages = array_map(
            static fn (CaseMessage $message): array => [
                'message_id' => $message->messageId(),
                'author_reference' => $message->authorReference(),
                'body' => $message->body(),
                'channel' => $message->channel(),
                'created_at' => $message->createdAt()->format(DATE_ATOM),
                'translation_state' => $message->translationState(),
                'attachment_ids' => $message->attachmentIds(),
            ],
            $case->thread()->requesterVisible()
        );

        return [
            'case_id' => $case->caseId()->value(),
            'category' => $case->categoryKey(),
            'state' => $case->state()->value,
            'version' => $case->version(),
            'sla_summary' => $case->slaSummary(),
            'messages' => $messages,
            'attachment_ids' => array_keys($case->attachments()),
            'can_reopen' => $case->state() === \Sabri\CF02\Domain\CaseState::Closed,
        ];
    }
}
