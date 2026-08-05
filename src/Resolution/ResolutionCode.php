<?php

declare(strict_types=1);

namespace Sabri\CF02\Resolution;

enum ResolutionCode: string
{
    case InformationProvided = 'information_provided';
    case UserActionCompleted = 'user_action_completed';
    case NativeOwnerActionCompleted = 'native_owner_action_completed';
    case DuplicateMerged = 'duplicate_merged';
    case NotReproducible = 'not_reproducible';
    case OutOfScopeReferred = 'out_of_scope_referred';
}
