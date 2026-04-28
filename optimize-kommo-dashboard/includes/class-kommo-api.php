<?php

if (! defined('ABSPATH')) {
    exit;
}

class Optimize_Kommo_API
{
    public static function get_redirect_uri()
    {
        return trim((string) get_option('optimize_kommo_redirect_uri', ''));
    }

    private static function get_config()
    {
        $subdomain = sanitize_text_field((string) get_option('optimize_kommo_subdomain', ''));
        $token = (string) get_option('optimize_kommo_token', '');

        if ('' === $subdomain || '' === $token) {
            return new WP_Error('missing_credentials', __('Subdomínio ou token da Kommo não configurado.', 'optimize-kommo-dashboard'));
        }

        return [
            'subdomain' => $subdomain,
            'token'     => $token,
        ];
    }

    private static function get_oauth_config()
    {
        $subdomain = sanitize_text_field((string) get_option('optimize_kommo_subdomain', ''));
        $client_id = trim((string) get_option('optimize_kommo_client_id', ''));
        $client_secret = trim((string) get_option('optimize_kommo_client_secret', ''));
        $redirect_uri = self::get_redirect_uri();
        $refresh_token = trim((string) get_option('optimize_kommo_refresh_token', ''));

        if ('' === $subdomain || '' === $client_id || '' === $client_secret || '' === $redirect_uri || '' === $refresh_token) {
            return new WP_Error('missing_oauth_credentials', __('Credenciais OAuth incompletas para renovar token.', 'optimize-kommo-dashboard'));
        }

        return [
            'subdomain'     => $subdomain,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'refresh_token' => $refresh_token,
        ];
    }

    public static function refresh_access_token()
    {
        $config = self::get_oauth_config();
        if (is_wp_error($config)) {
            return $config;
        }

        $endpoint = sprintf('https://%s.kommo.com/oauth2/access_token', rawurlencode($config['subdomain']));
        $payload = [
            'client_id'     => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'grant_type'    => 'refresh_token',
            'refresh_token' => $config['refresh_token'],
            'redirect_uri'  => $config['redirect_uri'],
        ];

        $response = wp_remote_post(
            $endpoint,
            [
                'timeout' => 30,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body_raw = (string) wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);
        if (! is_array($body) || $status >= 400) {
            return new WP_Error('oauth_refresh_error', sprintf('Falha ao renovar token (%d): %s', $status, $body_raw));
        }

        $access_token = sanitize_text_field((string) ($body['access_token'] ?? ''));
        if ('' === $access_token) {
            return new WP_Error('oauth_refresh_error', __('Kommo não retornou access_token no refresh.', 'optimize-kommo-dashboard'));
        }

        update_option('optimize_kommo_token', $access_token);

        if (! empty($body['refresh_token'])) {
            update_option('optimize_kommo_refresh_token', sanitize_text_field((string) $body['refresh_token']));
        }

        if (! empty($body['expires_in'])) {
            update_option('optimize_kommo_token_expires_at', (int) time() + absint($body['expires_in']));
        }

        return true;
    }

    private static function request($path)
    {
        $config = self::get_config();
        if (is_wp_error($config)) {
            return $config;
        }

        $endpoint = sprintf('https://%s.kommo.com%s', rawurlencode($config['subdomain']), $path);

        $response = wp_remote_get(
            $endpoint,
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $config['token'],
                    'Content-Type'  => 'application/json',
                ],
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if (401 === $status) {
            $refresh = self::refresh_access_token();
            if (is_wp_error($refresh)) {
                return $refresh;
            }

            $config = self::get_config();
            if (is_wp_error($config)) {
                return $config;
            }

            $response = wp_remote_get(
                $endpoint,
                [
                    'timeout' => 30,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $config['token'],
                        'Content-Type'  => 'application/json',
                    ],
                ]
            );

            if (is_wp_error($response)) {
                return $response;
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = json_decode((string) wp_remote_retrieve_body($response), true);
        }

        if ($status >= 400) {
            return new WP_Error('api_error', sprintf('Erro da Kommo (%d): %s', $status, wp_json_encode($body)));
        }

        return is_array($body) ? $body : [];
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
        $loss_reasons_response = self::request('/api/v4/leads/loss_reasons');
        if (is_wp_error($loss_reasons_response)) {
            return $loss_reasons_response;
        }

        $users = [];
        foreach (($users_response['_embedded']['users'] ?? []) as $user) {
            $users[(int) ($user['id'] ?? 0)] = sanitize_text_field((string) ($user['name'] ?? ''));
        }

        $pipelines = [];
        $statuses = [];
        $loss_reasons = [];

        foreach (($pipelines_response['_embedded']['pipelines'] ?? []) as $pipeline) {
            $pipeline_id = (int) ($pipeline['id'] ?? 0);
            $pipelines[$pipeline_id] = sanitize_text_field((string) ($pipeline['name'] ?? ''));

            foreach (($pipeline['_embedded']['statuses'] ?? []) as $status) {
                $statuses[(int) ($status['id'] ?? 0)] = sanitize_text_field((string) ($status['name'] ?? ''));
            }
        }

        foreach (($loss_reasons_response['_embedded']['loss_reasons'] ?? []) as $reason) {
            $loss_reasons[(int) ($reason['id'] ?? 0)] = sanitize_text_field((string) ($reason['name'] ?? ''));
        }

        return [
            'users'     => $users,
            'pipelines' => $pipelines,
            'statuses'  => $statuses,
            'loss_reasons' => $loss_reasons,
        ];
    }
}
