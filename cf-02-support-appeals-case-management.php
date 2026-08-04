<?php
/**
 * Plugin Name: Sabri Support, Appeals and Case Management (Conditional)
 * Plugin URI: https://sabrihomeopathy.com/
 * Description: Conditional, fail-closed support, case, SLA, appeal, evidence, migration and resilience runtime for the Sabri Social Homeopathy Platform.
 * Version: 1.0.0-rc.4
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Dr. Allamah Majid Hussain Sabri
 * Text Domain: cf-02-support-appeals-case-management
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('CF02_VERSION', '1.0.0-rc.4');
define('CF02_PLAN_VERSION', '1.0');
define('CF02_PLUGIN_FILE', __FILE__);
define('CF02_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CF02_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CF02_PLUGIN_DIR . 'src/Autoload.php';

\Sabri\CF02\Autoload::register(CF02_PLUGIN_DIR . 'src');

register_activation_hook(CF02_PLUGIN_FILE, static function (): void {
    \Sabri\CF02\Plugin::activate();
});

register_deactivation_hook(CF02_PLUGIN_FILE, static function (): void {
    \Sabri\CF02\Infrastructure\WordPress\Scheduler::unschedule();
});

add_action('plugins_loaded', static function (): void {
    \Sabri\CF02\Plugin::boot();
});
