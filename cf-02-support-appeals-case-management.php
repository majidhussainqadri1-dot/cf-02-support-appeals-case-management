<?php
/**
 * Plugin Name: Sabri Support, Appeals and Case Management (Conditional)
 * Plugin URI: https://sabrihomeopathy.com/
 * Description: Conditional, fail-closed foundation for support cases, SLA queues, escalations, evidence-bound appeals, and native-owner implementation reconciliation.
 * Version: 0.2.0-alpha.1
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Dr. Allamah Majid Hussain Sabri
 * Text Domain: cf-02-support-appeals-case-management
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('CF02_VERSION', '0.2.0-alpha.1');
define('CF02_PLAN_VERSION', '1.0');
define('CF02_PLUGIN_FILE', __FILE__);
define('CF02_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CF02_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CF02_PLUGIN_DIR . 'src/Autoload.php';

\Sabri\CF02\Autoload::register(CF02_PLUGIN_DIR . 'src');

register_activation_hook(CF02_PLUGIN_FILE, static function (): void {
    \Sabri\CF02\Plugin::activate();
});

add_action('plugins_loaded', static function (): void {
    \Sabri\CF02\Plugin::boot();
});
