<?php

if (! defined('ABSPATH')) {
    exit;
}

class Optimize_Kommo_DB
{
    public static function leads_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'optimize_kommo_leads';
    }

    public static function logs_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'optimize_kommo_sync_logs';
    }

    public static function create_tables()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $leads = self::leads_table();
        $logs = self::logs_table();

        $sql_leads = "CREATE TABLE {$leads} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            kommo_lead_id BIGINT(20) UNSIGNED NOT NULL,
            lead_name VARCHAR(255) DEFAULT '',
            contact_name VARCHAR(255) DEFAULT '',
            responsible_user VARCHAR(255) DEFAULT '',
            pipeline_name VARCHAR(255) DEFAULT '',
            status_name VARCHAR(255) DEFAULT '',
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            tags TEXT NULL,
            bu VARCHAR(80) DEFAULT 'Não classificado',
            origem VARCHAR(80) DEFAULT 'Outros',
            faixa_faturamento VARCHAR(180) DEFAULT '',
            loss_reason_id BIGINT(20) UNSIGNED DEFAULT 0,
            loss_reason_name VARCHAR(255) DEFAULT '',
            non_advance_category VARCHAR(180) DEFAULT '',
            score_diagnostico VARCHAR(80) DEFAULT '',
            setor_atuacao VARCHAR(180) DEFAULT '',
            link_relatorio TEXT NULL,
            origem_confessada VARCHAR(180) DEFAULT '',
            utm_source VARCHAR(180) DEFAULT '',
            utm_medium VARCHAR(180) DEFAULT '',
            utm_campaign VARCHAR(180) DEFAULT '',
            utm_content VARCHAR(180) DEFAULT '',
            utm_term VARCHAR(180) DEFAULT '',
            raw_payload LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY kommo_lead_id (kommo_lead_id),
            KEY created_at (created_at),
            KEY bu (bu),
            KEY origem (origem),
            KEY status_name (status_name),
            KEY pipeline_name (pipeline_name),
            KEY loss_reason_name (loss_reason_name),
            KEY non_advance_category (non_advance_category)
        ) {$charset_collate};";

        $sql_logs = "CREATE TABLE {$logs} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            sync_started_at DATETIME NOT NULL,
            sync_finished_at DATETIME NULL,
            status VARCHAR(30) NOT NULL,
            total_fetched INT(11) DEFAULT 0,
            total_created INT(11) DEFAULT 0,
            total_updated INT(11) DEFAULT 0,
            error_message TEXT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY sync_started_at (sync_started_at)
        ) {$charset_collate};";

        dbDelta($sql_leads);
        dbDelta($sql_logs);
    }
}
