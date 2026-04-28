<?php

if (! defined('ABSPATH')) {
    exit;
}

class Optimize_Kommo_Dashboard
{
    public static function init()
    {
        add_shortcode('optimize_kommo_dashboard', [__CLASS__, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('wp_ajax_optimize_kommo_get_dashboard_data', [__CLASS__, 'ajax_data']);
    }

    public static function enqueue()
    {
        wp_register_style(
            'optimize-kommo-dashboard-css',
            OPTIMIZE_KOMMO_DASHBOARD_URL . 'assets/css/dashboard.css',
            [],
            OPTIMIZE_KOMMO_DASHBOARD_VERSION
        );

        wp_register_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], '4.4.1', true);

        wp_register_script(
            'optimize-kommo-dashboard-js',
            OPTIMIZE_KOMMO_DASHBOARD_URL . 'assets/js/dashboard.js',
            ['jquery', 'chart-js'],
            OPTIMIZE_KOMMO_DASHBOARD_VERSION,
            true
        );
    }

    public static function user_can_access()
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        $allowed = (string) get_option('optimize_kommo_authorized_users', '');
        $ids = array_filter(array_map('absint', array_map('trim', explode(',', $allowed))));

        return in_array(get_current_user_id(), $ids, true);
    }

    public static function render_shortcode()
    {
        if (! is_user_logged_in() || ! self::user_can_access()) {
            return '<p>' . esc_html__('Você não tem permissão para visualizar este dashboard.', 'optimize-kommo-dashboard') . '</p>';
        }

        wp_enqueue_style('optimize-kommo-dashboard-css');
        wp_enqueue_script('optimize-kommo-dashboard-js');

        wp_localize_script(
            'optimize-kommo-dashboard-js',
            'OptimizeKommoDashboard',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('optimize_kommo_dashboard_nonce'),
            ]
        );

        ob_start();
        include OPTIMIZE_KOMMO_DASHBOARD_PATH . 'templates/dashboard.php';

        return ob_get_clean();
    }

    public static function ajax_data()
    {
        check_ajax_referer('optimize_kommo_dashboard_nonce', 'nonce');

        if (! is_user_logged_in() || ! self::user_can_access()) {
            wp_send_json_error(['message' => __('Sem permissão.', 'optimize-kommo-dashboard')], 403);
        }

        global $wpdb;
        $table = Optimize_Kommo_DB::leads_table();

        $where = ['1=1'];
        $params = [];

        $request = wp_unslash($_POST);

        $date_start = sanitize_text_field($request['date_start'] ?? '');
        $date_end   = sanitize_text_field($request['date_end'] ?? '');

        $map_filters = [
            'pipeline_name'     => 'pipeline',
            'status_name'       => 'status',
            'bu'                => 'bu',
            'origem'            => 'origem',
            'responsible_user'  => 'responsible_user',
            'faixa_faturamento' => 'faixa_faturamento',
        ];

        if ('' !== $date_start) {
            $where[] = 'DATE(created_at) >= %s';
            $params[] = $date_start;
        }

        if ('' !== $date_end) {
            $where[] = 'DATE(created_at) <= %s';
            $params[] = $date_end;
        }

        foreach ($map_filters as $db_column => $request_key) {
            $value = sanitize_text_field($request[$request_key] ?? '');
            if ('' !== $value) {
                $where[] = "{$db_column} = %s";
                $params[] = $value;
            }
        }

        $where_sql = implode(' AND ', $where);
        $base_sql = "FROM {$table} WHERE {$where_sql}";

        $total = (int) $wpdb->get_var(self::prepare_query("SELECT COUNT(*) {$base_sql}", $params));
        $qualificados = (int) $wpdb->get_var(self::prepare_query("SELECT COUNT(*) {$base_sql} AND status_name NOT LIKE %s", array_merge($params, ['%Desqualificado%'])));
        $desqualificados = (int) $wpdb->get_var(self::prepare_query("SELECT COUNT(*) {$base_sql} AND (status_name LIKE %s OR tags LIKE %s)", array_merge($params, ['%Desqualificado%', '%Desqualificado%'])));
        $agendados = (int) $wpdb->get_var(self::prepare_query("SELECT COUNT(*) {$base_sql} AND (status_name LIKE %s OR tags LIKE %s)", array_merge($params, ['%Agendado%', '%Agendado%'])));
        $acima_20m = (int) $wpdb->get_var(self::prepare_query("SELECT COUNT(*) {$base_sql} AND faixa_faturamento REGEXP %s", array_merge($params, ['(2[0-9]|[3-9][0-9]).*(mi|milh|MM)'])));

        $rows = $wpdb->get_results(
            self::prepare_query(
                "SELECT lead_name, created_at, responsible_user, pipeline_name, status_name, bu, origem, faixa_faturamento, link_relatorio {$base_sql} ORDER BY created_at DESC LIMIT 300",
                $params
            ),
            ARRAY_A
        );

        $charts = [
            'by_day' => self::group_count($rows, static function ($row) {
                return substr((string) $row['created_at'], 0, 10);
            }),
            'by_origem' => self::group_count($rows, static function ($row) {
                return (string) ($row['origem'] ?: 'N/A');
            }),
            'by_bu' => self::group_count($rows, static function ($row) {
                return (string) ($row['bu'] ?: 'N/A');
            }),
            'by_faixa' => self::group_count($rows, static function ($row) {
                return (string) ($row['faixa_faturamento'] ?: 'N/A');
            }),
            'by_status' => self::group_count($rows, static function ($row) {
                return (string) ($row['status_name'] ?: 'N/A');
            }),
            'by_pipeline' => self::group_count($rows, static function ($row) {
                return (string) ($row['pipeline_name'] ?: 'N/A');
            }),
        ];

        wp_send_json_success(
            [
                'cards' => [
                    'total'          => $total,
                    'periodo'        => $total,
                    'qualificados'   => $qualificados,
                    'desqualificados'=> $desqualificados,
                    'agendados'      => $agendados,
                    'acima_20m'      => $acima_20m,
                    'por_bu'         => $charts['by_bu'],
                    'por_origem'     => $charts['by_origem'],
                ],
                'charts' => $charts,
                'table'  => $rows,
            ]
        );
    }

    private static function prepare_query($sql, array $params)
    {
        global $wpdb;

        if (empty($params)) {
            return $sql;
        }

        return $wpdb->prepare($sql, $params);
    }

    private static function group_count(array $rows, callable $label_callback)
    {
        $counts = [];

        foreach ($rows as $row) {
            $label = (string) $label_callback($row);
            if (! isset($counts[$label])) {
                $counts[$label] = 0;
            }
            $counts[$label]++;
        }

        return $counts;
    }
}
