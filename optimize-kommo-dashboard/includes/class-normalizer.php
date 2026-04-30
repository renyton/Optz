<?php

if (! defined('ABSPATH')) {
    exit;
}

class Optimize_Kommo_Normalizer
{
    public static function normalize_lead(array $lead, array $lookups = [])
    {
        $tags = self::extract_tags($lead);
        $custom_fields = self::extract_custom_fields($lead);

        $responsible_id = absint($lead['responsible_user_id'] ?? 0);
        $pipeline_id = absint($lead['pipeline_id'] ?? 0);
        $status_id = absint($lead['status_id'] ?? 0);

        $created = ! empty($lead['created_at']) ? gmdate('Y-m-d H:i:s', (int) $lead['created_at']) : null;
        $updated = ! empty($lead['updated_at']) ? gmdate('Y-m-d H:i:s', (int) $lead['updated_at']) : null;

        $status_name = (string) ($custom_fields['Etapa do lead'] ?? '');
        if ('' === $status_name) {
            $status_name = (string) ($lookups['statuses'][$status_id] ?? $status_id);
        }

        $pipeline_name = sanitize_text_field((string) ($lookups['pipelines'][$pipeline_id] ?? $pipeline_id));
        $faixa_faturamento = sanitize_text_field((string) ($custom_fields['Faixa Faturamento'] ?? ''));
        $loss_reason_id = absint($lead['loss_reason_id'] ?? 0);
        $loss_reason_name = sanitize_text_field((string) ($lookups['loss_reasons'][$loss_reason_id] ?? ''));
        $non_advance_category = self::classify_non_advance_category($pipeline_name, $status_name, $faixa_faturamento, $loss_reason_name);
        $meeting_data = self::extract_meeting_time_data($status_name, $created, $updated);

        $debug_payload = [
            'lead_id' => absint($lead['id'] ?? 0),
            'status_name' => sanitize_text_field($status_name),
            'pipeline_name' => $pipeline_name,
            'loss_reason_id' => $loss_reason_id,
            'loss_reason_name' => $loss_reason_name,
            'lead_payload' => $lead,
        ];

        return [
            'kommo_lead_id'      => absint($lead['id'] ?? 0),
            'lead_name'          => sanitize_text_field((string) ($lead['name'] ?? '')),
            'contact_name'       => sanitize_text_field((string) self::extract_primary_contact_name($lead)),
            'responsible_user'   => sanitize_text_field((string) ($lookups['users'][$responsible_id] ?? $responsible_id)),
            'pipeline_name'      => $pipeline_name,
            'status_name'        => sanitize_text_field($status_name),
            'created_at'         => $created,
            'updated_at'         => $updated,
            'tags'               => wp_json_encode($tags, JSON_UNESCAPED_UNICODE),
            'bu'                 => self::classify_bu($tags),
            'origem'             => self::classify_origem($tags),
            'faixa_faturamento'  => $faixa_faturamento,
            'loss_reason_id'     => $loss_reason_id,
            'loss_reason_name'   => $loss_reason_name,
            'non_advance_category' => $non_advance_category,
            'meeting_scheduled_at' => $meeting_data['meeting_scheduled_at'],
            'time_to_meeting_minutes' => $meeting_data['time_to_meeting_minutes'],
            'time_to_meeting_hours' => $meeting_data['time_to_meeting_hours'],
            'time_to_meeting_days' => $meeting_data['time_to_meeting_days'],
            'time_to_meeting_source' => $meeting_data['time_to_meeting_source'],
            'score_diagnostico'  => sanitize_text_field((string) ($custom_fields['Score Diagnóstico'] ?? '')),
            'setor_atuacao'      => sanitize_text_field((string) ($custom_fields['Setor Atuação'] ?? '')),
            'link_relatorio'     => esc_url_raw((string) ($custom_fields['Link Relatório'] ?? '')),
            'origem_confessada'  => sanitize_text_field((string) ($custom_fields['Origem Confessada'] ?? '')),
            'utm_source'         => sanitize_text_field((string) ($custom_fields['utm_source'] ?? '')),
            'utm_medium'         => sanitize_text_field((string) ($custom_fields['utm_medium'] ?? '')),
            'utm_campaign'       => sanitize_text_field((string) ($custom_fields['utm_campaign'] ?? '')),
            'utm_content'        => sanitize_text_field((string) ($custom_fields['utm_content'] ?? '')),
            'utm_term'           => sanitize_text_field((string) ($custom_fields['utm_term'] ?? '')),
            'raw_payload'        => wp_json_encode($debug_payload, JSON_UNESCAPED_UNICODE),
        ];
    }

    private static function extract_tags(array $lead)
    {
        $tags = $lead['_embedded']['tags'] ?? [];
        if (empty($tags) && ! empty($lead['tags']) && is_array($lead['tags'])) {
            $tags = $lead['tags'];
        }

        return array_values(
            array_filter(
                array_map(
                    static function ($tag) {
                        return sanitize_text_field((string) ($tag['name'] ?? ''));
                    },
                    $tags
                )
            )
        );
    }

    private static function extract_primary_contact_name(array $lead)
    {
        $contacts = $lead['_embedded']['contacts'] ?? [];
        $first = $contacts[0] ?? [];

        return (string) ($first['name'] ?? '');
    }

    private static function extract_custom_fields(array $lead)
    {
        $result = [];

        foreach (($lead['custom_fields_values'] ?? []) as $field) {
            $name = sanitize_text_field((string) ($field['field_name'] ?? ''));
            $value = $field['values'][0]['value'] ?? '';

            if ('' !== $name) {
                $result[$name] = is_scalar($value) ? (string) $value : wp_json_encode($value);
            }
        }

        return $result;
    }

    public static function classify_bu(array $tags)
    {
        $tag_string = mb_strtolower(implode('|', $tags));
        $rules = [
            'Consulting' => ['consulting'],
            'Accounting' => ['account', 'contabilidade'],
            'Jurídico'   => ['juridico', 'jurídico'],
            'Marketing'  => ['marketing'],
            'Tech'       => ['tech'],
        ];

        foreach ($rules as $bu => $needles) {
            foreach ($needles as $needle) {
                if (false !== mb_strpos($tag_string, $needle)) {
                    return $bu;
                }
            }
        }

        return 'Não classificado';
    }

    public static function classify_origem(array $tags)
    {
        $tag_string = mb_strtolower(implode('|', $tags));

        if (false !== mb_strpos($tag_string, mb_strtolower('Diagnóstico Online'))) {
            return 'Diagnóstico Online';
        }

        if (false !== mb_strpos($tag_string, 'forms-grupo')) {
            return 'Forms-grupo';
        }

        return 'Outros';
    }

    private static function normalize_text($value)
    {
        $text = strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)));
        return remove_accents($text);
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
            return (float) preg_replace('/[^\d]/', '', $matches[0]);
        }

        return 0;
    }

    public static function classify_non_advance_category($pipeline_name, $status_name, $faixa_faturamento, $loss_reason_name)
    {
        if (self::normalize_text($pipeline_name) !== self::normalize_text('SDR | Grupo Optimize')) {
            return '';
        }

        if (self::normalize_text($status_name) !== self::normalize_text('NÃO AVANÇOU')) {
            return '';
        }

        $reason = self::normalize_text($loss_reason_name);
        $revenue = self::estimate_revenue_value($faixa_faturamento);

        $low_revenue_keywords = ['abaixo de 1 milhao', 'abaixo de 1 milhão', 'menor que 1 milhao', 'menor que 1 milhão', 'menos de 1 milhao', 'menos de 1 milhão', 'faturamento abaixo', 'baixa receita', 'baixo faturamento'];
        foreach ($low_revenue_keywords as $keyword) {
            if (false !== strpos($reason, self::normalize_text($keyword))) {
                return 'Desqualificado por faturamento';
            }
        }

        if ($revenue > 0 && $revenue < 1000000) {
            return 'Desqualificado por faturamento';
        }

        $recovery_keywords = ['sem resposta', 'sem retorno', 'nao respondeu', 'não respondeu', 'nao interagiu', 'não interagiu', 'fup sem resposta', 'follow up sem resposta', 'base de recuperacao', 'base de recuperação'];
        foreach ($recovery_keywords as $keyword) {
            if (false !== strpos($reason, self::normalize_text($keyword))) {
                return 'Base de recuperação';
            }
        }

        if ('' !== $reason) {
            return 'Não avançou - sem motivo identificado';
        }

        return 'Não avançou - sem motivo identificado';
    }

    private static function extract_meeting_time_data($status_name, $created_at, $updated_at)
    {
        $status_normalized = self::normalize_text($status_name);
        $meeting_statuses = [
            self::normalize_text('CLOSER - REUNIÃO AGENDADA'),
            self::normalize_text('SDR - AGENDADO COM O SDR'),
        ];

        if (! in_array($status_normalized, $meeting_statuses, true) || empty($created_at) || empty($updated_at)) {
            return [
                'meeting_scheduled_at' => null,
                'time_to_meeting_minutes' => null,
                'time_to_meeting_hours' => null,
                'time_to_meeting_days' => null,
                'time_to_meeting_source' => 'unavailable',
            ];
        }

        $created_ts = strtotime((string) $created_at);
        $updated_ts = strtotime((string) $updated_at);
        if (! $created_ts || ! $updated_ts || $updated_ts < $created_ts) {
            return [
                'meeting_scheduled_at' => null,
                'time_to_meeting_minutes' => null,
                'time_to_meeting_hours' => null,
                'time_to_meeting_days' => null,
                'time_to_meeting_source' => 'unavailable',
            ];
        }

        $minutes = (int) round(($updated_ts - $created_ts) / 60);

        return [
            'meeting_scheduled_at' => gmdate('Y-m-d H:i:s', $updated_ts),
            'time_to_meeting_minutes' => $minutes,
            'time_to_meeting_hours' => round($minutes / 60, 2),
            'time_to_meeting_days' => round($minutes / 1440, 2),
            'time_to_meeting_source' => 'estimated_updated_at',
        ];
    }
}
