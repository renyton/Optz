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
        add_shortcode('optimize_kommo_login', [__CLASS__, 'render_login_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('wp_ajax_optimize_kommo_get_dashboard_data', [__CLASS__, 'ajax_data']);
        add_action('admin_init', [__CLASS__, 'block_viewer_admin_access']);
        add_action('init', [__CLASS__, 'handle_login_submission']);
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
        return current_user_can('access_optimize_dashboard') || current_user_can('manage_options');
    }

    public static function render_shortcode()
    {
        if (! is_user_logged_in()) {
            wp_safe_redirect(self::get_login_page_url());
            exit;
        }

        if (! self::user_can_access()) {
            return '<p>' . esc_html__('Acesso não autorizado.', 'optimize-kommo-dashboard') . '</p>';
        }

        wp_enqueue_style('optimize-kommo-dashboard-css');
        wp_enqueue_script('optimize-kommo-dashboard-js');

        wp_localize_script(
            'optimize-kommo-dashboard-js',
            'OptimizeKommoDashboard',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('optimize_kommo_dashboard_nonce'),
                'logoutUrl' => wp_logout_url(self::get_login_page_url()),
            ]
        );

        ob_start();
        include OPTIMIZE_KOMMO_DASHBOARD_PATH . 'templates/dashboard.php';

        return ob_get_clean();
    }

    public static function render_login_shortcode()
    {
        wp_enqueue_style('optimize-kommo-dashboard-css');

        if (is_user_logged_in() && self::user_can_access()) {
            wp_safe_redirect(self::get_dashboard_page_url());
            exit;
        }

        $error_key = sanitize_text_field((string) ($_GET['okd_login_error'] ?? ''));
        $error_map = [
            'invalid_nonce' => __('Sessão inválida. Tente novamente.', 'optimize-kommo-dashboard'),
            'empty_fields' => __('Informe usuário/e-mail e senha.', 'optimize-kommo-dashboard'),
            'invalid_login' => __('Usuário ou senha inválidos.', 'optimize-kommo-dashboard'),
            'not_allowed' => __('Seu usuário não possui acesso ao dashboard.', 'optimize-kommo-dashboard'),
        ];
        $message = $error_map[$error_key] ?? '';

        ob_start();
        ?>
        <div class="okd-login-wrap">
            <form method="post" class="okd-login-form">
                <h2><?php esc_html_e('Login Dashboard Comercial', 'optimize-kommo-dashboard'); ?></h2>
                <?php if ('' !== $message) : ?>
                    <p class="okd-login-error"><?php echo esc_html($message); ?></p>
                <?php endif; ?>
                <input type="hidden" name="okd_login_action" value="1" />
                <?php wp_nonce_field('okd_login_nonce_action', 'okd_login_nonce'); ?>
                <p>
                    <label for="okd_login_username"><?php esc_html_e('Usuário ou e-mail', 'optimize-kommo-dashboard'); ?></label>
                    <input type="text" id="okd_login_username" name="okd_login_username" required />
                </p>
                <p>
                    <label for="okd_login_password"><?php esc_html_e('Senha', 'optimize-kommo-dashboard'); ?></label>
                    <input type="password" id="okd_login_password" name="okd_login_password" required />
                </p>
                <p><button type="submit" class="button button-primary"><?php esc_html_e('Entrar', 'optimize-kommo-dashboard'); ?></button></p>
            </form>
        </div>
        <?php

        return ob_get_clean();
    }

    public static function handle_login_submission()
    {
        if ('POST' !== strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
            return;
        }

        if (empty($_POST['okd_login_action'])) {
            return;
        }

        if (! isset($_POST['okd_login_nonce']) || ! wp_verify_nonce(sanitize_text_field((string) $_POST['okd_login_nonce']), 'okd_login_nonce_action')) {
            wp_safe_redirect(add_query_arg('okd_login_error', 'invalid_nonce', self::get_login_page_url()));
            exit;
        }

        $username = sanitize_text_field((string) ($_POST['okd_login_username'] ?? ''));
        $password = (string) ($_POST['okd_login_password'] ?? '');
        if ('' === $username || '' === $password) {
            wp_safe_redirect(add_query_arg('okd_login_error', 'empty_fields', self::get_login_page_url()));
            exit;
        }

        if (is_email($username)) {
            $user = get_user_by('email', $username);
            if ($user instanceof WP_User) {
                $username = $user->user_login;
            }
        }

        $signed = wp_signon(
            [
                'user_login' => $username,
                'user_password' => $password,
                'remember' => true,
            ],
            is_ssl()
        );

        if (is_wp_error($signed)) {
            wp_safe_redirect(add_query_arg('okd_login_error', 'invalid_login', self::get_login_page_url()));
            exit;
        }

        if (! user_can($signed, 'access_optimize_dashboard') && ! user_can($signed, 'manage_options')) {
            wp_logout();
            wp_safe_redirect(add_query_arg('okd_login_error', 'not_allowed', self::get_login_page_url()));
            exit;
        }

        wp_safe_redirect(self::get_dashboard_page_url());
        exit;
    }

    public static function block_viewer_admin_access()
    {
        if (! is_user_logged_in() || wp_doing_ajax()) {
            return;
        }

        if (current_user_can('manage_options')) {
            return;
        }

        if (current_user_can('access_optimize_dashboard')) {
            wp_safe_redirect(self::get_dashboard_page_url());
            exit;
        }
    }

    public static function ajax_data()
    {
        check_ajax_referer('optimize_kommo_dashboard_nonce', 'nonce');

        if (! is_user_logged_in() || ! self::user_can_access()) {
            wp_send_json_error(['message' => __('Sem permissão.', 'optimize-kommo-dashboard')], 403);
        }

        global $wpdb;
        $table = Optimize_Kommo_DB::leads_table();

        $request = wp_unslash($_POST);
        $rows = self::get_filtered_leads($request);

        $total = count($rows);
        $qualificados = 0;
        $desqualificados = 0;
        $agendados = 0;
        $acima_20m = 0;
        $desqualificados_faturamento = 0;
        $base_recuperacao = 0;
        $sem_motivo_identificado = 0;
        $total_nao_avancaram = 0;
        $meeting_minutes = [];
        $leads_sem_reuniao = 0;

        foreach ($rows as $row) {
            if (self::is_qualified_lead($row)) {
                $qualificados++;
            }

            if (self::is_desqualificado_por_faturamento_row($row, (string) ($row['non_advance_category'] ?? ''), (string) ($row['loss_reason_name'] ?? ''))) {
                $desqualificados++;
            }

            if (self::contains_keyword((string) ($row['status_name'] ?? ''), ['agendado'])) {
                $agendados++;
            }

            if (self::estimate_revenue_value((string) ($row['faixa_faturamento'] ?? '')) >= 20000000) {
                $acima_20m++;
            }

            $category = (string) ($row['non_advance_category'] ?? '');
            $loss_reason_name = (string) ($row['loss_reason_name'] ?? '');
            if ('NÃO AVANÇOU' === mb_strtoupper((string) ($row['status_name'] ?? ''), 'UTF-8')) {
                $total_nao_avancaram++;
            }
            if (self::is_desqualificado_por_faturamento_row($row, $category, $loss_reason_name)) {
                $desqualificados_faturamento++;
            } elseif (self::is_base_recuperacao_row($row, $category, $loss_reason_name)) {
                $base_recuperacao++;
            } elseif ('Não avançou - sem motivo identificado' === $category) {
                $sem_motivo_identificado++;
            }

            $minutes = isset($row['time_to_meeting_minutes']) ? (int) $row['time_to_meeting_minutes'] : 0;
            if ($minutes > 0) {
                $meeting_minutes[] = $minutes;
            } else {
                $leads_sem_reuniao++;
            }
        }
        sort($meeting_minutes);
        $meeting_avg = ! empty($meeting_minutes) ? round(array_sum($meeting_minutes) / count($meeting_minutes), 2) : 0;
        $meeting_min = ! empty($meeting_minutes) ? min($meeting_minutes) : 0;
        $meeting_max = ! empty($meeting_minutes) ? max($meeting_minutes) : 0;
        $meeting_median = 0;
        if (! empty($meeting_minutes)) {
            $mid = (int) floor(count($meeting_minutes) / 2);
            $meeting_median = (count($meeting_minutes) % 2) ? $meeting_minutes[$mid] : round(($meeting_minutes[$mid - 1] + $meeting_minutes[$mid]) / 2, 2);
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
            'non_advance_reasons' => self::group_count($rows, static function ($row) {
                $category = (string) ($row['non_advance_category'] ?? '');
                if ('' === trim($category)) {
                    return 'Outros';
                }
                return $category;
            }),
            'loss_reasons' => self::group_count($rows, static function ($row) {
                $reason = trim((string) ($row['loss_reason_name'] ?? ''));
                return '' === $reason ? 'Sem motivo informado' : $reason;
            }),
        ];

        if (self::normalize_text($request['pipeline'] ?? '') === self::normalize_text(self::SDR_PIPELINE)) {
            $charts['by_status'] = self::order_status_map_for_sdr($charts['by_status']);
        }

        $table_rows = array_slice($rows, 0, 300);
        $filter_options = self::build_filter_options($request);
        update_option('optimize_kommo_dashboard_last_debug', [
            'total_leads_filtrados' => $total,
            'total_nao_avancou' => $total_nao_avancaram,
            'total_com_loss_reason' => count(array_filter($rows, static function ($r) { return '' !== trim((string) ($r['loss_reason_name'] ?? '')); })),
            'total_com_non_advance_category' => count(array_filter($rows, static function ($r) { return '' !== trim((string) ($r['non_advance_category'] ?? '')); })),
            'total_desqualificados' => $desqualificados_faturamento,
            'total_base_recuperacao' => $base_recuperacao,
            'updated_at' => current_time('mysql'),
        ]);

        wp_send_json_success(
            [
                'cards' => [
                    'total'          => $total,
                    'periodo'        => $total,
                    'qualificados'   => $qualificados,
                    'desqualificados'=> $desqualificados,
                    'leads_desqualificados'=> $desqualificados,
                    'agendados'      => $agendados,
                    'acima_20m'      => $acima_20m,
                    'desqualificados_faturamento' => $desqualificados_faturamento,
                    'base_recuperacao' => $base_recuperacao,
                    'sem_motivo_identificado' => $sem_motivo_identificado,
                    'total_nao_avancaram' => $total_nao_avancaram,
                    'por_origem'     => $charts['by_origem'],
                    'meeting_avg_minutes' => $meeting_avg,
                    'meeting_median_minutes' => $meeting_median,
                    'meeting_min_minutes' => $meeting_min,
                    'meeting_max_minutes' => $meeting_max,
                    'leads_sem_reuniao' => $leads_sem_reuniao,
                ],
                'charts' => $charts,
                'table'  => $table_rows,
                'filter_options' => $filter_options,
                'lossReasonsChart' => $charts['loss_reasons'],
                'nonAdvanceCategoryChart' => $charts['non_advance_reasons'],
                'nonAdvanceKpis' => [
                    'totalNaoAvancaram' => $total_nao_avancaram,
                    'desqualificadosPorFaturamento' => $desqualificados_faturamento,
                    'baseRecuperacao' => $base_recuperacao,
                    'semMotivoIdentificado' => $sem_motivo_identificado,
                ],
                'filterOptions' => [
                    'lossReasons' => (array) ($filter_options['loss_reason_name'] ?? []),
                    'nonAdvanceCategories' => (array) ($filter_options['non_advance_category'] ?? []),
                ],
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

    private static function get_filtered_leads(array $request)
    {
        global $wpdb;
        $table = Optimize_Kommo_DB::leads_table();
        $where = ['1=1'];
        $params = [];

        $date_start = sanitize_text_field($request['date_start'] ?? '');
        $date_end   = sanitize_text_field($request['date_end'] ?? '');
        $map_filters = [
            'pipeline_name'     => 'pipeline',
            'status_name'       => 'status',
            'bu'                => 'bu',
            'origem'            => 'origem',
            'responsible_user'  => 'responsible_user',
            'faixa_faturamento' => 'faixa_faturamento',
            'loss_reason_name'  => 'loss_reason_name',
            'non_advance_category' => 'non_advance_category',
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
        return $wpdb->get_results(
            self::prepare_query(
                "SELECT lead_name, created_at, responsible_user, pipeline_name, status_name, bu, origem, faixa_faturamento, link_relatorio, loss_reason_name, non_advance_category, meeting_scheduled_at, time_to_meeting_minutes, time_to_meeting_source {$base_sql} ORDER BY created_at DESC",
                $params
            ),
            ARRAY_A
        );
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
            'loss_reason_name' => 'loss_reason_name',
            'non_advance_category' => 'non_advance_category',
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

    private static function is_desqualificado_por_faturamento_row(array $row, $category, $loss_reason_name)
    {
        if ('NÃO AVANÇOU' !== mb_strtoupper((string) ($row['status_name'] ?? ''), 'UTF-8')) {
            return false;
        }
        if ('Desqualificado por faturamento' === $category) {
            return true;
        }
        if (self::estimate_revenue_value((string) ($row['faixa_faturamento'] ?? '')) > 0 && self::estimate_revenue_value((string) ($row['faixa_faturamento'] ?? '')) < 1000000) {
            return true;
        }
        return self::contains_keyword($loss_reason_name, ['abaixo de 1 milhao', 'abaixo de 1 milhão', 'menor que 1 milhao', 'menor que 1 milhão', 'menos de 1 milhao', 'menos de 1 milhão', 'faturamento abaixo', 'baixa receita', 'baixo faturamento']);
    }

    private static function is_base_recuperacao_row(array $row, $category, $loss_reason_name)
    {
        if ('NÃO AVANÇOU' !== mb_strtoupper((string) ($row['status_name'] ?? ''), 'UTF-8')) {
            return false;
        }
        if ('Base de recuperação' === $category) {
            return true;
        }
        return self::contains_keyword($loss_reason_name, ['sem resposta', 'sem retorno', 'não respondeu', 'nao respondeu', 'não interagiu', 'nao interagiu', 'fup sem resposta', 'follow up sem resposta', 'base de recuperação', 'base de recuperacao']);
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

    public static function get_login_page_url()
    {
        $page_id = self::find_page_with_shortcode('optimize_kommo_login');
        if ($page_id > 0) {
            return get_permalink($page_id);
        }

        return wp_login_url();
    }

    public static function get_dashboard_page_url()
    {
        $page_id = self::find_page_with_shortcode('optimize_kommo_dashboard');
        if ($page_id > 0) {
            return get_permalink($page_id);
        }

        return home_url('/');
    }

    private static function find_page_with_shortcode($shortcode)
    {
        $pages = get_pages(['post_status' => ['publish', 'private']]);
        foreach ($pages as $page) {
            if (isset($page->post_content) && false !== strpos((string) $page->post_content, '[' . $shortcode . ']')) {
                return (int) $page->ID;
            }
        }

        return 0;
    }
}
