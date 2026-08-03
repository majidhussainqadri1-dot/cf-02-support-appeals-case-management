<?php

declare(strict_types=1);

namespace Sabri\CF02\Activation;

interface ActivationEvidence
{
    public function runtimeSwitchEnabled(): bool;

    /** @return array<string, mixed> */
    public function founderApproval(): array;

    /** @return array<string, bool> */
    public function dependencyReadiness(): array;

    /** @return array<string, mixed> */
    public function operationalEvidence(): array;
}
