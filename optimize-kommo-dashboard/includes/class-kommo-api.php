<?php

if (! defined('ABSPATH')) {
    exit;
}

class Optimize_Kommo_API
{
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
