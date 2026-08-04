<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use Sabri\CF02\Search\CursorCodec;
use Sabri\CF02\Security\DataCipher;

final class Runtime
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        Installer::install();
        RoleRegistrar::register();
        RouteRegistrar::register();
        CompleteFrontendSurfaces::register();
        CompleteAdminSurfaces::register();
        RepairService::register();

        $keyRing = ManagedKeyRingProvider::current();
        $cipher = new DataCipher($keyRing);
        $operations = new OperationsRepository();
        $worker = new RuntimeWorker($operations, $cipher);
        Scheduler::register($worker);

        (new CompleteRestOverlay(new CursorCodec($keyRing->activeMaterial())))->register();

        add_action('rest_api_init', static function () use ($cipher, $operations): void {
            $cases = new CaseRepository();
            (new ComprehensiveRestController($cases, $operations, $cipher))->registerRoutes();
            (new ProviderWebhookController($cases, $operations, $cipher))->registerRoutes();
        });

        add_filter('wp_robots', static function (array $robots): array {
            if (self::isPrivateRoute()) {
                $robots['noindex'] = true;
                $robots['noarchive'] = true;
                $robots['nofollow'] = true;
            }
            return $robots;
        });

        add_action('send_headers', static function (): void {
            if (!self::isPrivateRoute()) {
                return;
            }
            nocache_headers();
            header('Cache-Control: private, no-store, max-age=0, must-revalidate', true);
            header('Pragma: no-cache', true);
            header('Referrer-Policy: no-referrer', true);
            header('X-Robots-Tag: noindex, noarchive, nofollow', true);
            header('X-Content-Type-Options: nosniff', true);
            header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()", true);
            header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self'", true);
        });

        do_action('cf02_runtime_booted', [
            'version' => CF02_VERSION,
            'schema_version' => SchemaCompletion::VERSION,
            'contract_version' => \Sabri\CF02\Contracts\SupportContractCatalog::CONTRACT_VERSION,
            'routes' => RouteRegistrar::contracts(),
            'key_provider' => $keyRing->provider(),
            'active_key_id' => $keyRing->activeKeyId(),
            'rotation_reference' => $keyRing->rotationReference(),
        ]);
    }

    private static function isPrivateRoute(): bool
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        return str_starts_with($path, '/support/cases/')
            || str_starts_with($path, '/support/appeals/')
            || (str_starts_with($path, '/wp-admin/admin.php') && isset($_GET['page']) && str_starts_with((string) $_GET['page'], 'cf02-support'))
            || str_starts_with($path, '/wp-json/cf02/v1/')
            || str_starts_with($path, '/wp-json/api/support/v1/')
            || str_starts_with($path, '/api/support/v1/');
    }
}
