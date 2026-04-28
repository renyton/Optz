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
        add_action('admin_post_optimize_kommo_oauth_start', [__CLASS__, 'handle_oauth_start']);
        add_action('admin_post_optimize_kommo_oauth_callback', [__CLASS__, 'handle_oauth_callback']);
        add_action('admin_post_optimize_kommo_create_viewer', [__CLASS__, 'handle_create_viewer']);
        add_action('admin_post_optimize_kommo_reset_viewer_password', [__CLASS__, 'handle_reset_viewer_password']);
        add_action('admin_post_optimize_kommo_remove_viewer_access', [__CLASS__, 'handle_remove_viewer_access']);
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
        register_setting('optimize_kommo_settings', 'optimize_kommo_redirect_uri', ['sanitize_callback' => [__CLASS__, 'sanitize_redirect_uri']]);
        register_setting('optimize_kommo_settings', 'optimize_kommo_token', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('optimize_kommo_settings', 'optimize_kommo_refresh_token', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('optimize_kommo_settings', 'optimize_kommo_interval', ['sanitize_callback' => 'absint']);
        register_setting('optimize_kommo_settings', 'optimize_kommo_authorized_users', ['sanitize_callback' => [__CLASS__, 'sanitize_authorized_users']]);
    }

    public static function sanitize_redirect_uri($value)
    {
        return trim(wp_kses_no_null((string) $value));
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
        $oauth_callback = admin_url('admin-post.php?action=optimize_kommo_oauth_callback');
        $oauth_debug = get_option('optimize_kommo_oauth_debug', []);
        $dashboard_users = get_users(
            [
                'role__in' => ['optimize_dashboard_viewer', 'administrator'],
                'orderby' => 'display_name',
                'order' => 'ASC',
            ]
        );

        include OPTIMIZE_KOMMO_DASHBOARD_PATH . 'templates/admin-settings.php';
    }

    public static function handle_create_viewer()
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('Sem permissão.', 'optimize-kommo-dashboard'));
        }

        check_admin_referer('optimize_kommo_create_viewer');

        $name = sanitize_text_field((string) ($_POST['viewer_name'] ?? ''));
        $email = sanitize_email((string) ($_POST['viewer_email'] ?? ''));
        $login = sanitize_user((string) ($_POST['viewer_login'] ?? ''), true);
        $password = (string) ($_POST['viewer_password'] ?? '');

        if ('' === $login || '' === $email || '' === $password) {
            wp_safe_redirect(add_query_arg('viewer_error', 'missing_fields', admin_url('admin.php?page=optimize-kommo-dashboard')));
            exit;
        }

        $user_id = wp_insert_user(
            [
                'user_login' => $login,
                'user_pass' => $password,
                'user_email' => $email,
                'display_name' => $name ?: $login,
                'role' => 'optimize_dashboard_viewer',
            ]
        );

        if (is_wp_error($user_id)) {
            wp_safe_redirect(add_query_arg('viewer_error', rawurlencode($user_id->get_error_message()), admin_url('admin.php?page=optimize-kommo-dashboard')));
            exit;
        }

        wp_safe_redirect(add_query_arg('viewer_success', 'created', admin_url('admin.php?page=optimize-kommo-dashboard')));
        exit;
    }

    public static function handle_reset_viewer_password()
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('Sem permissão.', 'optimize-kommo-dashboard'));
        }

        check_admin_referer('optimize_kommo_reset_viewer_password');

        $user_id = absint($_POST['user_id'] ?? 0);
        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            wp_safe_redirect(add_query_arg('viewer_error', 'user_not_found', admin_url('admin.php?page=optimize-kommo-dashboard')));
            exit;
        }

        $new_password = wp_generate_password(12, false);
        wp_set_password($new_password, $user_id);
        clean_user_cache($user_id);

        wp_safe_redirect(add_query_arg(['viewer_success' => 'password_reset', 'viewer_password' => rawurlencode($new_password), 'viewer_user' => rawurlencode($user->user_login)], admin_url('admin.php?page=optimize-kommo-dashboard')));
        exit;
    }

    public static function handle_remove_viewer_access()
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('Sem permissão.', 'optimize-kommo-dashboard'));
        }

        check_admin_referer('optimize_kommo_remove_viewer_access');

        $user_id = absint($_POST['user_id'] ?? 0);
        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            wp_safe_redirect(add_query_arg('viewer_error', 'user_not_found', admin_url('admin.php?page=optimize-kommo-dashboard')));
            exit;
        }

        if (in_array('administrator', (array) $user->roles, true)) {
            wp_safe_redirect(add_query_arg('viewer_error', 'cannot_remove_admin', admin_url('admin.php?page=optimize-kommo-dashboard')));
            exit;
        }

        $user->remove_role('optimize_dashboard_viewer');
        $user->set_role('subscriber');

        wp_safe_redirect(add_query_arg('viewer_success', 'removed_access', admin_url('admin.php?page=optimize-kommo-dashboard')));
        exit;
    }

    public static function handle_oauth_start()
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('Sem permissão.', 'optimize-kommo-dashboard'));
        }

        check_admin_referer('optimize_kommo_oauth_start');

        $subdomain = sanitize_text_field((string) get_option('optimize_kommo_subdomain', ''));
        $client_id = trim((string) get_option('optimize_kommo_client_id', ''));
        $redirect_uri_raw = Optimize_Kommo_API::get_redirect_uri();

        if ('' === $subdomain || '' === $client_id || '' === $redirect_uri_raw) {
            wp_safe_redirect(add_query_arg('oauth_error', 'missing_oauth_config', admin_url('admin.php?page=optimize-kommo-dashboard')));
            exit;
        }

        $state = wp_generate_password(24, false, false);
        set_transient('optimize_kommo_oauth_state_' . $state, '1', 10 * MINUTE_IN_SECONDS);

        $oauth_url = add_query_arg(
            [
                'client_id' => $client_id,
                'state' => $state,
                'mode' => 'post_message',
                'response_type' => 'code',
                'redirect_uri' => $redirect_uri_raw,
            ],
            'https://www.kommo.com/oauth'
        );

        update_option(
            'optimize_kommo_oauth_debug',
            [
                'last_step' => 'authorize',
                'oauth_url' => $oauth_url,
                'redirect_uri_raw' => $redirect_uri_raw,
                'redirect_uri_sent' => $redirect_uri_raw,
                'updated_at' => current_time('mysql'),
            ]
        );

        wp_redirect($oauth_url);
        exit;
    }

    public static function handle_oauth_callback()
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('Sem permissão.', 'optimize-kommo-dashboard'));
        }

        $state = sanitize_text_field((string) ($_GET['state'] ?? ''));
        $code = sanitize_text_field((string) ($_GET['code'] ?? ''));

        if ('' === $state || '' === $code || false === get_transient('optimize_kommo_oauth_state_' . $state)) {
            wp_safe_redirect(add_query_arg('oauth_error', 'invalid_state_or_code', admin_url('admin.php?page=optimize-kommo-dashboard')));
            exit;
        }

        delete_transient('optimize_kommo_oauth_state_' . $state);

        $subdomain = sanitize_text_field((string) get_option('optimize_kommo_subdomain', ''));
        $client_id = trim((string) get_option('optimize_kommo_client_id', ''));
        $client_secret = trim((string) get_option('optimize_kommo_client_secret', ''));
        $redirect_uri_raw = Optimize_Kommo_API::get_redirect_uri();

        if ('' === $subdomain || '' === $client_id || '' === $client_secret || '' === $redirect_uri_raw) {
            wp_safe_redirect(add_query_arg('oauth_error', 'missing_oauth_config', admin_url('admin.php?page=optimize-kommo-dashboard')));
            exit;
        }

        $endpoint = sprintf('https://%s.kommo.com/oauth2/access_token', rawurlencode($subdomain));
        $payload = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect_uri_raw,
        ];

        update_option(
            'optimize_kommo_oauth_debug',
            [
                'last_step' => 'token_exchange',
                'oauth_url' => '',
                'redirect_uri_raw' => $redirect_uri_raw,
                'redirect_uri_sent' => $redirect_uri_raw,
                'token_endpoint' => $endpoint,
                'payload' => $payload,
                'updated_at' => current_time('mysql'),
            ]
        );

        $response = wp_remote_post(
            $endpoint,
            [
                'timeout' => 30,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($response)) {
            wp_safe_redirect(add_query_arg('oauth_error', rawurlencode($response->get_error_message()), admin_url('admin.php?page=optimize-kommo-dashboard')));
            exit;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body_raw = (string) wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);

        if ($status >= 400 || ! is_array($body)) {
            update_option('optimize_kommo_oauth_debug', array_merge((array) get_option('optimize_kommo_oauth_debug', []), ['token_error' => $body_raw]));
            wp_safe_redirect(add_query_arg('oauth_error', rawurlencode('token_exchange_failed'), admin_url('admin.php?page=optimize-kommo-dashboard')));
            exit;
        }

        update_option('optimize_kommo_token', sanitize_text_field((string) ($body['access_token'] ?? '')));
        update_option('optimize_kommo_refresh_token', sanitize_text_field((string) ($body['refresh_token'] ?? '')));
        update_option('optimize_kommo_token_expires_at', (int) time() + absint($body['expires_in'] ?? 0));

        update_option('optimize_kommo_oauth_debug', array_merge((array) get_option('optimize_kommo_oauth_debug', []), ['token_success' => true]));

        wp_safe_redirect(add_query_arg('oauth_success', '1', admin_url('admin.php?page=optimize-kommo-dashboard')));
        exit;
    }
}
