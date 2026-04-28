<?php

if (! defined('ABSPATH')) {
    exit;
}

class Optimize_Kommo_Dashboard
{
    private const SDR_PIPELINE = 'SDR | Grupo Optimize';
    private const SDR_QUALIFIED_STATUSES = [
        'QUALIFICADO MAS AINDA NÃO AGENDOU',
        'CLOSER - REUNIÃO AGENDADA',
    ];
    private const SDR_STATUS_ORDER = [
        'INCOMING LEADS',
        'SDR - CONTATO INICIAL',
        'SDR - AGENDADO COM O SDR',
        'SDR - FUP SEM RESPOSTAS',
        'SDR - QUALIFICAÇÃO INICIADA',
        'SDR - NO SHOW SDR',
        'QUALIFICADO MAS AINDA NÃO AGENDOU',
        'CLOSER - REUNIÃO AGENDADA',
        'NÃO AVANÇOU',
    ];

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

        $rows = $wpdb->get_results(
            self::prepare_query(
                "SELECT lead_name, created_at, responsible_user, pipeline_name, status_name, bu, origem, faixa_faturamento, link_relatorio {$base_sql} ORDER BY created_at DESC",
                $params
            ),
            ARRAY_A
        );

        $total = count($rows);
        $qualificados = 0;
        $desqualificados = 0;
        $agendados = 0;
        $acima_20m = 0;

        foreach ($rows as $row) {
            if (self::is_qualified_lead($row)) {
                $qualificados++;
            }

            if (self::is_disqualified_lead($row)) {
                $desqualificados++;
            }

            if (self::contains_keyword((string) ($row['status_name'] ?? ''), ['agendado'])) {
                $agendados++;
            }

            if (self::estimate_revenue_value((string) ($row['faixa_faturamento'] ?? '')) >= 20000000) {
                $acima_20m++;
            }
        }

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

        if (self::normalize_text($request['pipeline'] ?? '') === self::normalize_text(self::SDR_PIPELINE)) {
            $charts['by_status'] = self::order_status_map_for_sdr($charts['by_status']);
        }

        $table_rows = array_slice($rows, 0, 300);

        wp_send_json_success(
            [
                'cards' => [
                    'total'          => $total,
                    'periodo'        => $total,
                    'qualificados'   => $qualificados,
                    'desqualificados'=> $desqualificados,
                    'agendados'      => $agendados,
                    'acima_20m'      => $acima_20m,
                    'por_origem'     => $charts['by_origem'],
                ],
                'charts' => $charts,
                'table'  => $table_rows,
                'filter_options' => self::build_filter_options($request),
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

    private static function build_filter_options(array $request)
    {
        global $wpdb;
        $table = Optimize_Kommo_DB::leads_table();

        $columns = [
            'pipeline' => 'pipeline_name',
            'status' => 'status_name',
            'bu' => 'bu',
            'origem' => 'origem',
            'responsible_user' => 'responsible_user',
            'faixa_faturamento' => 'faixa_faturamento',
        ];

        $options = [];
        foreach ($columns as $key => $column) {
            $values = $wpdb->get_col("SELECT DISTINCT {$column} FROM {$table} WHERE {$column} IS NOT NULL AND TRIM({$column}) <> ''");
            $values = array_values(array_filter(array_map('strval', $values)));

            if ('status' === $key && self::normalize_text($request['pipeline'] ?? '') === self::normalize_text(self::SDR_PIPELINE)) {
                $values = self::order_status_values_for_sdr($values);
            } else {
                natcasesort($values);
                $values = array_values($values);
            }

            $options[$key] = $values;
        }

        return $options;
    }

    private static function normalize_text($value)
    {
        $text = strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)));

        return remove_accents($text);
    }

    private static function contains_keyword($value, array $keywords)
    {
        $normalized = self::normalize_text($value);
        foreach ($keywords as $keyword) {
            if (false !== strpos($normalized, self::normalize_text($keyword))) {
                return true;
            }
        }

        return false;
    }

    private static function is_qualified_lead(array $row)
    {
        $pipeline = self::normalize_text($row['pipeline_name'] ?? '');
        $status = self::normalize_text($row['status_name'] ?? '');

        if ($pipeline === self::normalize_text(self::SDR_PIPELINE)) {
            foreach (self::SDR_QUALIFIED_STATUSES as $allowed) {
                if ($status === self::normalize_text($allowed)) {
                    return true;
                }
            }

            return false;
        }

        return ! self::contains_keyword((string) ($row['status_name'] ?? ''), ['desqualificado', 'nao avancou', 'não avançou', 'baixa']);
    }

    private static function is_disqualified_lead(array $row)
    {
        $status = (string) ($row['status_name'] ?? '');
        $is_low_status = self::contains_keyword($status, ['baixa', 'desqualificado', 'nao avancou', 'não avançou']);
        if (! $is_low_status) {
            return false;
        }

        $revenue = self::estimate_revenue_value((string) ($row['faixa_faturamento'] ?? ''));

        return $revenue > 0 && $revenue < 1000000;
    }

    private static function estimate_revenue_value($raw_value)
    {
        $value = self::normalize_text($raw_value);
        if ('' === $value) {
            return 0;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(mi|milhao|milhoes|milhaoes|mm)\b/u', $value, $matches)) {
            return (float) str_replace(',', '.', $matches[1]) * 1000000;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*mil\b/u', $value, $matches)) {
            return (float) str_replace(',', '.', $matches[1]) * 1000;
        }

        if (preg_match('/\d[\d\.\,]*/u', $value, $matches)) {
            $numeric = preg_replace('/[^\d]/', '', $matches[0]);
            return (float) $numeric;
        }

        return 0;
    }

    private static function order_status_values_for_sdr(array $values)
    {
        $normalized_map = [];
        foreach ($values as $value) {
            $normalized_map[self::normalize_text($value)] = $value;
        }

        $ordered = [];
        foreach (self::SDR_STATUS_ORDER as $status) {
            $key = self::normalize_text($status);
            if (isset($normalized_map[$key])) {
                $ordered[] = $normalized_map[$key];
                unset($normalized_map[$key]);
            }
        }

        if (! empty($normalized_map)) {
            $remaining = array_values($normalized_map);
            natcasesort($remaining);
            $ordered = array_merge($ordered, array_values($remaining));
        }

        return $ordered;
    }

    private static function order_status_map_for_sdr(array $map)
    {
        $ordered_keys = self::order_status_values_for_sdr(array_keys($map));
        $ordered_map = [];

        foreach ($ordered_keys as $key) {
            if (isset($map[$key])) {
                $ordered_map[$key] = $map[$key];
            }
        }

        return $ordered_map;
    }
}
