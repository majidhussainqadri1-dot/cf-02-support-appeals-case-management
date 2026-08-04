<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

/**
 * Publishes route contracts to File 01/File 20 and supplies a guarded fallback.
 * File 20 remains the shell/layout owner; CF-02 never creates a second shell.
 */
final class RouteRegistrar
{
    /** @return array<string,array<string,mixed>> */
    public static function contracts(): array
    {
        return [
            '/support' => ['surface' => 'support', 'privacy' => 'mixed', 'cache' => 'public-help/private-user', 'owner' => 'CF-02'],
            '/support/cases/{id}' => ['surface' => 'case', 'privacy' => 'private', 'cache' => 'no-store', 'owner' => 'CF-02'],
            '/support/appeals/{id}' => ['surface' => 'appeal', 'privacy' => 'private', 'cache' => 'no-store', 'owner' => 'CF-02'],
            '/admin/support' => ['surface' => 'operations', 'privacy' => 'restricted', 'cache' => 'no-store', 'owner' => 'CF-02'],
            '/admin/support/cases/{id}' => ['surface' => 'workbench', 'privacy' => 'restricted', 'cache' => 'no-store', 'owner' => 'CF-02'],
            '/admin/support/quality' => ['surface' => 'quality', 'privacy' => 'restricted', 'cache' => 'no-store', 'owner' => 'CF-02'],
            '/api/support/v1/*' => ['surface' => 'api', 'privacy' => 'scoped', 'cache' => 'no-store', 'owner' => 'CF-02'],
        ];
    }

    public static function register(): void
    {
        add_action('init', static function (): void {
            foreach (self::contracts() as $route => $contract) {
                do_action('sabri_register_route_contract', $route, array_merge($contract, [
                    'contract_version' => \Sabri\CF02\Contracts\SupportContractCatalog::CONTRACT_VERSION,
                    'module_version' => CF02_VERSION,
                ]));
            }
            do_action('cf02_route_contracts_ready', self::contracts());
        }, 20);

        add_filter('cf02_route_contracts', static fn (array $contracts): array => array_merge($contracts, self::contracts()));
    }
}
