<?php

if (! defined('ABSPATH')) {
    exit;
}

class Optimize_Kommo_Admin
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('update_option_optimize_kommo_interval', [__CLASS__, 'on_interval_update'], 10, 2);
    }

    public static function menu()
    {
        add_menu_page(
            __('Optimize Kommo', 'optimize-kommo-dashboard'),
            __('Optimize Kommo', 'optimize-kommo-dashboard'),
            'manage_options',
            'optimize-kommo-dashboard',
            [__CLASS__, 'render_page'],
            'dashicons-chart-area'
        );
    }

    public static function register_settings()
    {
        register_setting('optimize_kommo_settings', 'optimize_kommo_subdomain', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('optimize_kommo_settings', 'optimize_kommo_token', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('optimize_kommo_settings', 'optimize_kommo_interval', ['sanitize_callback' => 'absint']);
        register_setting('optimize_kommo_settings', 'optimize_kommo_authorized_users', ['sanitize_callback' => [__CLASS__, 'sanitize_authorized_users']]);
    }

    public static function sanitize_authorized_users($value)
    {
        $raw = array_map('trim', explode(',', (string) $value));
        $ids = array_filter(array_map('absint', $raw));

        return implode(',', $ids);
    }

    public static function on_interval_update($old_value, $new_value)
    {
        if ((int) $old_value !== (int) $new_value) {
            Optimize_Kommo_Sync::schedule_event(true);
        }
    }

    public static function enqueue($hook)
    {
        if ('toplevel_page_optimize-kommo-dashboard' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'optimize-kommo-admin-js',
            OPTIMIZE_KOMMO_DASHBOARD_URL . 'assets/js/dashboard.js',
            ['jquery'],
            OPTIMIZE_KOMMO_DASHBOARD_VERSION,
            true
        );

        wp_localize_script(
            'optimize-kommo-admin-js',
            'OptimizeKommoAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('optimize_kommo_admin_nonce'),
            ]
        );
    }

    public static function render_page()
    {
        global $wpdb;

        $table_name = Optimize_Kommo_DB::logs_table();
        $logs = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 20", ARRAY_A);
        $last_sync = get_option('optimize_kommo_last_sync', __('Nunca', 'optimize-kommo-dashboard'));

        include OPTIMIZE_KOMMO_DASHBOARD_PATH . 'templates/admin-settings.php';
    }
}
