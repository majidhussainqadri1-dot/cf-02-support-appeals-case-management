<?php

declare(strict_types=1);

namespace Sabri\CF02\Attachment;

enum AttachmentState: string
{
    case Uploaded = 'uploaded';
    case Quarantined = 'quarantined';
    case Scanned = 'scanned';
    case Available = 'available';
    case Rejected = 'rejected';
    case Redacted = 'redacted';
    case Superseded = 'superseded';
    case Expired = 'expired';
    case Purged = 'purged';
}
