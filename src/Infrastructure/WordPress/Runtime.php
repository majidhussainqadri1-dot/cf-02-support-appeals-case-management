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
        if (self::$booted) { return; }

        // Security dependencies are resolved before any public/admin/runtime surface is registered.
        $keyRing = ManagedKeyRingProvider::current();
        $cipher = new DataCipher($keyRing);
        Installer::install();
        RoleRegistrar::register();

        $operations = new OperationsRepository();
        $worker = new RuntimeWorker($operations, $cipher);
        RouteRegistrar::register();
        CompleteFrontendSurfaces::register();
        CompleteAdminSurfaces::register();
        RepairService::register();
        Scheduler::register($worker);
        (new CompleteRestOverlay(new CursorCodec($keyRing->activeMaterial())))->register();

        add_action('rest_api_init', static function () use ($cipher, $operations): void {
            $cases = new CaseRepository();
            (new ComprehensiveRestController($cases, $operations, $cipher))->registerRoutes();
            (new ProviderWebhookController($cases, $operations, $cipher))->registerRoutes();
        });

        add_filter('wp_robots', static function (array $robots): array {
            if (self::isPrivateRoute()) { $robots['noindex']=true; $robots['noarchive']=true; $robots['nofollow']=true; }
            return $robots;
        });
        add_action('send_headers', static function (): void {
            if (!self::isPrivateRoute()) { return; }
            nocache_headers();
            header('Cache-Control: private, no-store, max-age=0, must-revalidate', true);
            header('Pragma: no-cache', true);
            header('Referrer-Policy: no-referrer', true);
            header('X-Robots-Tag: noindex, noarchive, nofollow', true);
            header('X-Content-Type-Options: nosniff', true);
            header('X-Frame-Options: SAMEORIGIN', true);
            header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()", true);
            header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; connect-src 'self'; img-src 'self' data:; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'", true);
        });

        self::$booted = true;
        do_action('cf02_runtime_booted', [
            'version'=>CF02_VERSION,'schema_version'=>SchemaCompletion::VERSION,
            'contract_version'=>\Sabri\CF02\Contracts\SupportContractCatalog::CONTRACT_VERSION,
            'routes'=>RouteRegistrar::contracts(),'key_provider'=>$keyRing->provider(),
            'active_key_id'=>$keyRing->activeKeyId(),'rotation_reference'=>$keyRing->rotationReference(),
        ]);
    }

    private static function isPrivateRoute(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = rawurldecode((string)parse_url($uri, PHP_URL_PATH));
        $path = '/'.ltrim((string)preg_replace('#/+#', '/', $path), '/');
        $adminPage = isset($_GET['page']) ? sanitize_key(wp_unslash((string)$_GET['page'])) : '';
        return str_starts_with($path, '/support/cases/')
            || str_starts_with($path, '/support/appeals/')
            || ($path === '/wp-admin/admin.php' && str_starts_with($adminPage, 'cf02-support'))
            || str_starts_with($path, '/wp-json/cf02/v1/')
            || str_starts_with($path, '/wp-json/api/support/v1/')
            || str_starts_with($path, '/api/support/v1/');
    }
}
