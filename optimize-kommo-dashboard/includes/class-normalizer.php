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

        return [
            'kommo_lead_id'      => absint($lead['id'] ?? 0),
            'lead_name'          => sanitize_text_field((string) ($lead['name'] ?? '')),
            'contact_name'       => sanitize_text_field((string) self::extract_primary_contact_name($lead)),
            'responsible_user'   => sanitize_text_field((string) ($lookups['users'][$responsible_id] ?? $responsible_id)),
            'pipeline_name'      => sanitize_text_field((string) ($lookups['pipelines'][$pipeline_id] ?? $pipeline_id)),
            'status_name'        => sanitize_text_field($status_name),
            'created_at'         => $created,
            'updated_at'         => $updated,
            'tags'               => wp_json_encode($tags, JSON_UNESCAPED_UNICODE),
            'bu'                 => self::classify_bu($tags),
            'origem'             => self::classify_origem($tags),
            'faixa_faturamento'  => sanitize_text_field((string) ($custom_fields['Faixa Faturamento'] ?? '')),
            'score_diagnostico'  => sanitize_text_field((string) ($custom_fields['Score Diagnóstico'] ?? '')),
            'setor_atuacao'      => sanitize_text_field((string) ($custom_fields['Setor Atuação'] ?? '')),
            'link_relatorio'     => esc_url_raw((string) ($custom_fields['Link Relatório'] ?? '')),
            'origem_confessada'  => sanitize_text_field((string) ($custom_fields['Origem Confessada'] ?? '')),
            'utm_source'         => sanitize_text_field((string) ($custom_fields['utm_source'] ?? '')),
            'utm_medium'         => sanitize_text_field((string) ($custom_fields['utm_medium'] ?? '')),
            'utm_campaign'       => sanitize_text_field((string) ($custom_fields['utm_campaign'] ?? '')),
            'utm_content'        => sanitize_text_field((string) ($custom_fields['utm_content'] ?? '')),
            'utm_term'           => sanitize_text_field((string) ($custom_fields['utm_term'] ?? '')),
            'raw_payload'        => wp_json_encode($lead, JSON_UNESCAPED_UNICODE),
        ];
    }

    private static function extract_tags(array $lead)
    {
        $tags = $lead['_embedded']['tags'] ?? [];

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
}
