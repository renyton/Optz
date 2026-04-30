<?php

if (! defined('ABSPATH')) {
    exit;
}

class Optimize_Kommo_Sync
{
    public static function init()
    {
        add_filter('cron_schedules', [__CLASS__, 'add_cron_interval']);
        add_action('init', [__CLASS__, 'schedule_event']);
        add_action('optimize_kommo_sync_event', [__CLASS__, 'run_sync']);
        add_action('wp_ajax_optimize_kommo_sync_now', [__CLASS__, 'ajax_sync_now']);
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook('optimize_kommo_sync_event');
    }

    public static function add_cron_interval($schedules)
    {
        $minutes = max(1, absint(get_option('optimize_kommo_interval', 10)));

        $schedules['optimize_kommo_interval'] = [
            'interval' => $minutes * MINUTE_IN_SECONDS,
            'display'  => sprintf(__('A cada %d minutos', 'optimize-kommo-dashboard'), $minutes),
        ];

        return $schedules;
    }

    public static function schedule_event($force = false)
    {
        if ($force) {
            wp_clear_scheduled_hook('optimize_kommo_sync_event');
        }

        if (! wp_next_scheduled('optimize_kommo_sync_event')) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'optimize_kommo_interval', 'optimize_kommo_sync_event');
        }
    }

    public static function ajax_sync_now()
    {
        check_ajax_referer('optimize_kommo_admin_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'optimize-kommo-dashboard')], 403);
        }

        $result = self::run_sync();
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 500);
        }

        wp_send_json_success($result);
    }

    public static function run_sync()
    {
        global $wpdb;

        $logs_table  = Optimize_Kommo_DB::logs_table();
        $leads_table = Optimize_Kommo_DB::leads_table();
        $started_at  = current_time('mysql');

        $wpdb->insert(
            $logs_table,
            [
                'sync_started_at' => $started_at,
                'status'          => 'running',
            ],
            ['%s', '%s']
        );

        $log_id = (int) $wpdb->insert_id;
        if ($log_id <= 0) {
            return new WP_Error('sync_log_insert_error', __('Não foi possível criar o log da sincronização.', 'optimize-kommo-dashboard'));
        }

        $fetched = 0;
        $created = 0;
        $updated = 0;
        $error_message = null;
        $status = 'error';
        $sync_debug = [
            'loss_reasons_total' => 0,
            'leads_with_loss_reason_id' => 0,
            'leads_nao_avancou_total' => 0,
            'leads_nao_avancou_without_loss_reason_id' => 0,
            'leads_with_tags' => 0,
            'leads_without_tags' => 0,
            'oauth_scope_hint' => '',
        ];

        try {
            $lookups = Optimize_Kommo_API::fetch_lookups();
            if (is_wp_error($lookups)) {
                $scope_hint_message = $lookups->get_error_message();
                if (false !== stripos($scope_hint_message, '403') || false !== stripos($scope_hint_message, '401')) {
                    $sync_debug['oauth_scope_hint'] = 'Possível falta de escopo OAuth para leitura de leads/loss_reasons.';
                }
                throw new RuntimeException($lookups->get_error_message());
            }
            $sync_debug['loss_reasons_total'] = count((array) ($lookups['loss_reasons'] ?? []));

            $leads = Optimize_Kommo_API::fetch_leads();
            if (is_wp_error($leads)) {
                throw new RuntimeException($leads->get_error_message());
            }

            $fetched = count($leads);

            foreach ($leads as $lead) {
                $normalized = Optimize_Kommo_Normalizer::normalize_lead($lead, $lookups);
                if (empty($normalized['kommo_lead_id'])) {
                    continue;
                }

                if (! empty($normalized['loss_reason_id'])) {
                    $sync_debug['leads_with_loss_reason_id']++;
                }
                if (! empty($normalized['tags']) && '[]' !== (string) $normalized['tags']) {
                    $sync_debug['leads_with_tags']++;
                } else {
                    $sync_debug['leads_without_tags']++;
                }

                if ('NÃO AVANÇOU' === mb_strtoupper((string) ($normalized['status_name'] ?? ''), 'UTF-8')) {
                    $sync_debug['leads_nao_avancou_total']++;
                    if (empty($normalized['loss_reason_id'])) {
                        $sync_debug['leads_nao_avancou_without_loss_reason_id']++;
                    }
                }

                $exists = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$leads_table} WHERE kommo_lead_id = %d",
                        $normalized['kommo_lead_id']
                    )
                );

                if ($exists > 0) {
                    $wpdb->update($leads_table, $normalized, ['id' => $exists]);
                    $updated++;
                } else {
                    $wpdb->insert($leads_table, $normalized);
                    $created++;
                }
            }

            $status = 'success';
            $error_message = wp_json_encode($sync_debug, JSON_UNESCAPED_UNICODE);
            update_option('optimize_kommo_last_sync', current_time('mysql'));
        } catch (Throwable $exception) {
            $error_message = sanitize_textarea_field($exception->getMessage()) . ' | DEBUG: ' . wp_json_encode($sync_debug, JSON_UNESCAPED_UNICODE);
        }

        $wpdb->update(
            $logs_table,
            [
                'sync_finished_at' => current_time('mysql'),
                'status'           => $status,
                'total_fetched'    => $fetched,
                'total_created'    => $created,
                'total_updated'    => $updated,
                'error_message'    => $error_message,
            ],
            ['id' => $log_id],
            ['%s', '%s', '%d', '%d', '%d', '%s'],
            ['%d']
        );

        if ('error' === $status) {
            return new WP_Error('sync_error', $error_message ?: __('Erro desconhecido.', 'optimize-kommo-dashboard'));
        }

        return [
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
        ];
    }
}
