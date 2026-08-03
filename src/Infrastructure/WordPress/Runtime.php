<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use RuntimeException;
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
        Scheduler::register();
        FrontendSurfaces::register();
        AdminSurfaces::register();

        add_action('rest_api_init', static function (): void {
            $keyMaterial = '';
            foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY'] as $constant) {
                if (defined($constant)) {
                    $keyMaterial .= (string) constant($constant);
                }
            }
            if (strlen($keyMaterial) < 32) {
                throw new RuntimeException('WordPress security keys are insufficient for CF-02 encrypted storage.');
            }
            $controller = new RestController(new CaseRepository(), new DataCipher($keyMaterial));
            $controller->registerRoutes();
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
            if (self::isPrivateRoute()) {
                nocache_headers();
                header('Cache-Control: private, no-store, max-age=0, must-revalidate', true);
                header('Pragma: no-cache', true);
                header('Referrer-Policy: no-referrer', true);
                header('X-Robots-Tag: noindex, noarchive, nofollow', true);
                header('X-Content-Type-Options: nosniff', true);
            }
        });

        do_action('cf02_runtime_booted', ['version' => CF02_VERSION, 'schema_version' => SchemaExtension::VERSION]);
    }

    private static function isPrivateRoute(): bool
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        return str_starts_with($path, '/support/cases/')
            || str_starts_with($path, '/support/appeals/')
            || str_starts_with($path, '/wp-admin/admin.php') && isset($_GET['page']) && $_GET['page'] === 'cf02-support'
            || str_starts_with($path, '/wp-json/cf02/v1/');
    }
}
