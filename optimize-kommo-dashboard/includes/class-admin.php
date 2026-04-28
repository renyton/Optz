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
        add_action('admin_init', [__CLASS__, 'handle_oauth_actions']);
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
        register_setting('optimize_kommo_settings', 'optimize_kommo_client_id', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('optimize_kommo_settings', 'optimize_kommo_client_secret', ['sanitize_callback' => 'sanitize_text_field']);
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

    public static function handle_oauth_actions()
    {
        if (! is_admin() || ! current_user_can('manage_options')) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ('optimize-kommo-dashboard' !== $page) {
            return;
        }

        $action = isset($_GET['optimize_kommo_oauth']) ? sanitize_text_field(wp_unslash($_GET['optimize_kommo_oauth'])) : '';
        $is_callback = isset($_GET['optimize_kommo_oauth_callback']);

        if ($is_callback) {
            self::handle_oauth_callback();
            return;
        }

        if ('disconnect' === $action) {
            check_admin_referer('optimize_kommo_disconnect');
            Optimize_Kommo_API::disconnect();
            self::redirect_with_notice('disconnected');
        }
    }

    private static function handle_oauth_callback()
    {
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $saved_state = sanitize_text_field((string) get_option('optimize_kommo_oauth_state', ''));

        if ('' === $code || '' === $state || '' === $saved_state || ! hash_equals($saved_state, $state)) {
            self::redirect_with_notice('oauth_state_error');
        }

        $result = Optimize_Kommo_API::exchange_authorization_code($code);
        if (is_wp_error($result)) {
            self::redirect_with_notice('oauth_error', $result->get_error_message());
        }

        delete_option('optimize_kommo_oauth_state');
        self::redirect_with_notice('connected');
    }

    private static function redirect_with_notice($status, $message = '')
    {
        $params = ['page' => 'optimize-kommo-dashboard', 'okd_notice' => $status];

        if ('' !== $message) {
            $params['okd_message'] = rawurlencode($message);
        }

        wp_safe_redirect(add_query_arg($params, admin_url('admin.php')));
        exit;
    }

    private static function get_notice()
    {
        $status = isset($_GET['okd_notice']) ? sanitize_text_field(wp_unslash($_GET['okd_notice'])) : '';
        $message = isset($_GET['okd_message']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['okd_message']))) : '';

        if ('' === $status) {
            return null;
        }

        $map = [
            'connected'        => ['success', __('Kommo conectada com sucesso via OAuth 2.0.', 'optimize-kommo-dashboard')],
            'disconnected'     => ['warning', __('Conexão com a Kommo removida.', 'optimize-kommo-dashboard')],
            'oauth_state_error' => ['error', __('Falha na validação do estado OAuth. Tente conectar novamente.', 'optimize-kommo-dashboard')],
            'oauth_error'      => ['error', __('Não foi possível concluir a autenticação com a Kommo.', 'optimize-kommo-dashboard')],
        ];

        $notice = $map[$status] ?? ['info', __('Operação concluída.', 'optimize-kommo-dashboard')];
        if ('' !== $message) {
            $notice[1] .= ' ' . $message;
        }

        return [
            'class'   => $notice[0],
            'message' => $notice[1],
        ];
    }

    public static function render_page()
    {
        global $wpdb;

        $table_name = Optimize_Kommo_DB::logs_table();
        $logs = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 20", ARRAY_A);
        $last_sync = get_option('optimize_kommo_last_sync', __('Nunca', 'optimize-kommo-dashboard'));

        $oauth_state = wp_generate_password(32, false, false);
        update_option('optimize_kommo_oauth_state', $oauth_state, false);

        $oauth_authorize_url = Optimize_Kommo_API::get_authorization_url($oauth_state);
        $oauth_status = Optimize_Kommo_API::get_connection_status();
        $oauth_notice = self::get_notice();
        $disconnect_url = wp_nonce_url(
            admin_url('admin.php?page=optimize-kommo-dashboard&optimize_kommo_oauth=disconnect'),
            'optimize_kommo_disconnect'
        );

        include OPTIMIZE_KOMMO_DASHBOARD_PATH . 'templates/admin-settings.php';
    }
}
