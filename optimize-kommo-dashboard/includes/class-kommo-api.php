<?php

if (! defined('ABSPATH')) {
    exit;
}

class Optimize_Kommo_API
{
    private static function get_base_config()
    {
        $subdomain = sanitize_text_field((string) get_option('optimize_kommo_subdomain', ''));
        $client_id = sanitize_text_field((string) get_option('optimize_kommo_client_id', ''));
        $client_secret = (string) get_option('optimize_kommo_client_secret', '');

        if ('' === $subdomain || '' === $client_id || '' === $client_secret) {
            return new WP_Error('missing_oauth_config', __('Subdomínio, Client ID ou Client Secret da Kommo não configurados.', 'optimize-kommo-dashboard'));
        }

        return [
            'subdomain'     => $subdomain,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
        ];
    }

    private static function get_base_url($subdomain)
    {
        return sprintf('https://%s.kommo.com', rawurlencode($subdomain));
    }

    public static function get_redirect_uri()
    {
        return admin_url('admin.php?page=optimize-kommo-dashboard&optimize_kommo_oauth_callback=1');
    }

    public static function get_authorization_url($state)
    {
        $config = self::get_base_config();
        if (is_wp_error($config)) {
            return $config;
        }

        $query = http_build_query(
            [
                'client_id'     => $config['client_id'],
                'state'         => $state,
                'response_type' => 'code',
                'mode'          => 'popup',
                'redirect_uri'  => self::get_redirect_uri(),
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        return 'https://www.kommo.com/oauth?' . $query;
    }

    private static function save_tokens($payload)
    {
        $access_token = sanitize_text_field((string) ($payload['access_token'] ?? ''));
        $refresh_token = sanitize_text_field((string) ($payload['refresh_token'] ?? ''));
        $expires_in = absint($payload['expires_in'] ?? 0);

        if ('' === $access_token || '' === $refresh_token || $expires_in <= 0) {
            return new WP_Error('invalid_token_payload', __('Resposta OAuth inválida da Kommo.', 'optimize-kommo-dashboard'));
        }

        update_option('optimize_kommo_oauth_access_token', $access_token, false);
        update_option('optimize_kommo_oauth_refresh_token', $refresh_token, false);
        update_option('optimize_kommo_oauth_expires_at', time() + $expires_in, false);
        update_option('optimize_kommo_oauth_connected_at', current_time('mysql'), false);

        return true;
    }

    private static function request_oauth_token($payload)
    {
        $config = self::get_base_config();
        if (is_wp_error($config)) {
            return $config;
        }

        $response = wp_remote_post(
            self::get_base_url($config['subdomain']) . '/oauth2/access_token',
            [
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body'    => wp_json_encode(
                    array_merge(
                        [
                            'client_id'     => $config['client_id'],
                            'client_secret' => $config['client_secret'],
                            'redirect_uri'  => self::get_redirect_uri(),
                        ],
                        $payload
                    )
                ),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($status >= 400) {
            return new WP_Error('oauth_error', sprintf('Erro OAuth Kommo (%d): %s', $status, wp_json_encode($body)));
        }

        return is_array($body) ? $body : [];
    }

    public static function exchange_authorization_code($authorization_code)
    {
        $code = sanitize_text_field((string) $authorization_code);
        if ('' === $code) {
            return new WP_Error('missing_code', __('Authorization code ausente.', 'optimize-kommo-dashboard'));
        }

        $token_payload = self::request_oauth_token(
            [
                'grant_type' => 'authorization_code',
                'code'       => $code,
            ]
        );

        if (is_wp_error($token_payload)) {
            return $token_payload;
        }

        return self::save_tokens($token_payload);
    }

    public static function refresh_access_token()
    {
        $refresh_token = sanitize_text_field((string) get_option('optimize_kommo_oauth_refresh_token', ''));
        if ('' === $refresh_token) {
            return new WP_Error('missing_refresh_token', __('Refresh token da Kommo não encontrado.', 'optimize-kommo-dashboard'));
        }

        $token_payload = self::request_oauth_token(
            [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token,
            ]
        );

        if (is_wp_error($token_payload)) {
            return $token_payload;
        }

        return self::save_tokens($token_payload);
    }

    private static function get_access_token()
    {
        $access_token = sanitize_text_field((string) get_option('optimize_kommo_oauth_access_token', ''));
        $expires_at = absint(get_option('optimize_kommo_oauth_expires_at', 0));

        if ('' === $access_token || $expires_at <= (time() + 60)) {
            $refreshed = self::refresh_access_token();
            if (is_wp_error($refreshed)) {
                return $refreshed;
            }

            $access_token = sanitize_text_field((string) get_option('optimize_kommo_oauth_access_token', ''));
        }

        if ('' === $access_token) {
            return new WP_Error('missing_access_token', __('Access token da Kommo não disponível.', 'optimize-kommo-dashboard'));
        }

        return $access_token;
    }

    private static function request($path, $method = 'GET', $body = null, $retry_on_unauthorized = true)
    {
        $config = self::get_base_config();
        if (is_wp_error($config)) {
            return $config;
        }

        $access_token = self::get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $endpoint = self::get_base_url($config['subdomain']) . $path;
        $args = [
            'method'  => strtoupper($method),
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
        ];

        if (null !== $body) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);

        if (401 === $status && $retry_on_unauthorized) {
            $refreshed = self::refresh_access_token();
            if (! is_wp_error($refreshed)) {
                return self::request($path, $method, $body, false);
            }
        }

        if ($status >= 400) {
            return new WP_Error('api_error', sprintf('Erro da Kommo (%d): %s', $status, wp_json_encode($decoded)));
        }

        return is_array($decoded) ? $decoded : [];
    }

    public static function disconnect()
    {
        delete_option('optimize_kommo_oauth_access_token');
        delete_option('optimize_kommo_oauth_refresh_token');
        delete_option('optimize_kommo_oauth_expires_at');
        delete_option('optimize_kommo_oauth_connected_at');
        delete_option('optimize_kommo_oauth_state');
    }

    public static function get_connection_status()
    {
        $access_token = (string) get_option('optimize_kommo_oauth_access_token', '');
        $refresh_token = (string) get_option('optimize_kommo_oauth_refresh_token', '');
        $expires_at = absint(get_option('optimize_kommo_oauth_expires_at', 0));

        return [
            'connected'    => '' !== $access_token && '' !== $refresh_token,
            'expires_at'   => $expires_at,
            'connected_at' => (string) get_option('optimize_kommo_oauth_connected_at', ''),
        ];
    }

    public static function fetch_leads()
    {
        $page = 1;
        $results = [];

        while ($page <= 100) {
            $body = self::request(sprintf('/api/v4/leads?limit=250&page=%d&with=contacts', $page));
            if (is_wp_error($body)) {
                return $body;
            }

            $items = $body['_embedded']['leads'] ?? [];
            if (empty($items)) {
                break;
            }

            $results = array_merge($results, $items);

            if (empty($body['_links']['next']['href'])) {
                break;
            }

            $page++;
        }

        return $results;
    }

    public static function fetch_lookups()
    {
        $users_response = self::request('/api/v4/users?limit=250');
        if (is_wp_error($users_response)) {
            return $users_response;
        }

        $pipelines_response = self::request('/api/v4/leads/pipelines');
        if (is_wp_error($pipelines_response)) {
            return $pipelines_response;
        }

        $users = [];
        foreach (($users_response['_embedded']['users'] ?? []) as $user) {
            $users[(int) ($user['id'] ?? 0)] = sanitize_text_field((string) ($user['name'] ?? ''));
        }

        $pipelines = [];
        $statuses = [];

        foreach (($pipelines_response['_embedded']['pipelines'] ?? []) as $pipeline) {
            $pipeline_id = (int) ($pipeline['id'] ?? 0);
            $pipelines[$pipeline_id] = sanitize_text_field((string) ($pipeline['name'] ?? ''));

            foreach (($pipeline['_embedded']['statuses'] ?? []) as $status) {
                $statuses[(int) ($status['id'] ?? 0)] = sanitize_text_field((string) ($status['name'] ?? ''));
            }
        }

        return [
            'users'     => $users,
            'pipelines' => $pipelines,
            'statuses'  => $statuses,
        ];
    }
}
