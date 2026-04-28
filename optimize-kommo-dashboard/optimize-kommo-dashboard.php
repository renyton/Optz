<?php
/**
 * Plugin Name: Optimize Kommo Dashboard
 * Description: Dashboard interno do Grupo Optimize com sincronização da Kommo.
 * Version: 1.0.0
 * Author: Grupo Optimize
 * Text Domain: optimize-kommo-dashboard
 */

if (! defined('ABSPATH')) {
    exit;
}

define('OPTIMIZE_KOMMO_DASHBOARD_VERSION', '1.0.0');
define('OPTIMIZE_KOMMO_DASHBOARD_PATH', plugin_dir_path(__FILE__));
define('OPTIMIZE_KOMMO_DASHBOARD_URL', plugin_dir_url(__FILE__));

require_once OPTIMIZE_KOMMO_DASHBOARD_PATH . 'includes/class-db.php';
require_once OPTIMIZE_KOMMO_DASHBOARD_PATH . 'includes/class-activator.php';
require_once OPTIMIZE_KOMMO_DASHBOARD_PATH . 'includes/class-kommo-api.php';
require_once OPTIMIZE_KOMMO_DASHBOARD_PATH . 'includes/class-normalizer.php';
require_once OPTIMIZE_KOMMO_DASHBOARD_PATH . 'includes/class-sync.php';
require_once OPTIMIZE_KOMMO_DASHBOARD_PATH . 'includes/class-admin.php';
require_once OPTIMIZE_KOMMO_DASHBOARD_PATH . 'includes/class-dashboard.php';

register_activation_hook(__FILE__, ['Optimize_Kommo_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Optimize_Kommo_Sync', 'deactivate']);

function optimize_kommo_dashboard_bootstrap()
{
    Optimize_Kommo_Activator::ensure_roles_and_caps();
    Optimize_Kommo_Admin::init();
    Optimize_Kommo_Sync::init();
    Optimize_Kommo_Dashboard::init();
}
add_action('plugins_loaded', 'optimize_kommo_dashboard_bootstrap');
