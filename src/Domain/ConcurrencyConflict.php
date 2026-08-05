<?php

declare(strict_types=1);

namespace Sabri\CF02\Domain;

use RuntimeException;

final class ConcurrencyConflict extends RuntimeException
{
}
