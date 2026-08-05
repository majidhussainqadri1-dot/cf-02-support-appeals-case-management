<?php

declare(strict_types=1);

namespace Sabri\CF02\Portal;

use DateTimeImmutable;
use Sabri\CF02\Attachment\AttachmentRecord;
use Sabri\CF02\Domain\CaseState;
use Sabri\CF02\Domain\CaseWorkspace;
use Sabri\CF02\Thread\CaseMessage;

final class UserCaseProjection
{
    /** @return array<string, mixed> */
    public static function fromWorkspace(CaseWorkspace $case, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $visibleAttachments = array_filter(
            $case->attachments(),
            static fn (AttachmentRecord $attachment): bool => $attachment->visibleToRequester($case->caseId())
        );
        $visibleAttachmentIds = array_keys($visibleAttachments);

        $messages = array_map(
            static fn (CaseMessage $message): array => [
                'message_id' => $message->messageId(),
                'author_label' => hash_equals($case->requesterReference(), $message->authorReference()) ? 'Requester' : 'Support Team',
                'body' => $message->body(),
                'channel' => $message->channel(),
                'created_at' => $message->createdAt()->format(DATE_ATOM),
                'translation_state' => $message->translationState(),
                'attachment_ids' => array_values(array_intersect($message->attachmentIds(), $visibleAttachmentIds)),
            ],
            $case->thread()->requesterVisible()
        );

        $resolution = $case->resolution();
        $canReopen = $case->state() === CaseState::Closed
            && $resolution !== null
            && $resolution->reopenUntil() > $now;

        return [
            'case_id' => $case->caseId()->value(),
            'category' => $case->categoryKey(),
            'state' => $case->state()->value,
            'version' => $case->version(),
            'sla_summary' => $case->slaSummary(),
            'messages' => $messages,
            'attachment_ids' => $visibleAttachmentIds,
            'can_reopen' => $canReopen,
        ];
    }
}
