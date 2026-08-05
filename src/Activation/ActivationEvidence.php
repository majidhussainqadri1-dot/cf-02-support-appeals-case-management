<?php

declare(strict_types=1);

namespace Sabri\CF02\Activation;

interface ActivationEvidence
{
    public function runtimeSwitchEnabled(): bool;

    /** `staging` may validate a candidate; `production` requires every external acceptance gate. */
    public function runtimeEnvironment(): string;

    /** @return array{runtime_version:string,schema_version:string,source_sha:string,package_sha256:string} */
    public function exactRuntimeIdentity(): array;

    /** @return array<string, mixed> */
    public function founderApproval(): array;

    /** @return array<string, array<string, mixed>> */
    public function dependencyReadiness(): array;

    /** @return array<string, mixed> */
    public function operationalEvidence(): array;
}
