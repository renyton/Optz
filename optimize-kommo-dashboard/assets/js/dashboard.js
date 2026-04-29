(function ($) {
    const charts = {};
    const chartState = {};
    const CHART_COLORS = {
        bu: ['#2563eb', '#1d4ed8', '#1e40af', '#3730a3', '#4338ca', '#64748b'],
        origem: ['#06b6d4', '#0ea5e9', '#0891b2', '#14b8a6', '#64748b'],
        status: ['#8b5cf6', '#7c3aed', '#6366f1', '#a855f7', '#64748b'],
        faixa: ['#f59e0b', '#d97706', '#f97316', '#fb7185', '#64748b'],
        pipeline: ['#10b981', '#059669', '#0d9488', '#14b8a6', '#64748b'],
        day: ['#2563eb'],
    };
    const CARD_ICONS = {
        'Total de leads': '📈',
        'Leads no período': '🗓️',
        'Leads qualificados': '✅',
        'Leads desqualificados': '🚫',
        'Reuniões agendadas': '📅',
        'Acima de R$ 20M/ano': '💎',
        'Leads por BU': '🏢',
        'Leads por origem': '🧭',
        'Desqualificados por faturamento': '💸',
        'Base de recuperação': '🔁',
        'Não avançaram sem motivo': '❓',
    };
    const SDR_PIPELINE_NAME = 'SDR | Grupo Optimize';
    const SDR_STATUS_ORDER = [
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

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function toDataset(map) {
        const labels = Object.keys(map || {});
        return {
            labels,
            data: labels.map((label) => map[label]),
        };
    }

    function safeChartIdToKey(chartId) {
        return String(chartId || '').replace('okd-chart-', '');
    }

    function normalizeLabel(value) {
        return String(value || '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLocaleLowerCase();
    }

    function isSdrPipeline(pipelineName) {
        return normalizeLabel(pipelineName) === normalizeLabel(SDR_PIPELINE_NAME);
    }

    function orderStatusMapForPipeline(map, pipelineName) {
        if (!isSdrPipeline(pipelineName)) {
            return map || {};
        }

        const source = map || {};
        const normalizedToOriginal = {};

        Object.keys(source).forEach((key) => {
            normalizedToOriginal[normalizeLabel(key)] = key;
        });

        const ordered = {};
        SDR_STATUS_ORDER.forEach((status) => {
            const match = normalizedToOriginal[normalizeLabel(status)];
            if (match) {
                ordered[match] = source[match];
            }
        });

        Object.keys(source).forEach((key) => {
            if (!Object.prototype.hasOwnProperty.call(ordered, key)) {
                ordered[key] = source[key];
            }
        });

        return ordered;
    }

    function chartKindById(id) {
        if (id.includes('origem')) return 'origem';
        if (id.includes('status')) return 'status';
        if (id.includes('faixa')) return 'faixa';
        if (id.includes('pipeline')) return 'pipeline';
        if (id.includes('non-advance')) return 'status';
        if (id.includes('bu')) return 'bu';
        return 'day';
    }

    function renderChart(id, title, map, activePipeline) {
        const canvas = document.getElementById(id);
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        const effectiveMap = id === 'okd-chart-status'
            ? orderStatusMapForPipeline(map, activePipeline)
            : (map || {});

        const ds = toDataset(effectiveMap);
        const kind = chartKindById(id);
        const palette = CHART_COLORS[kind] || CHART_COLORS.day;
        const selectedType = $(`.okd-chart-type[data-chart-id="${id}"]`).val() || (id === 'okd-chart-day' ? 'line' : 'bar');
        const isLine = selectedType === 'line';
        const isTable = selectedType === 'table';
        const barColors = ds.labels.map((_, i) => palette[i % palette.length]);
        const sheetId = `okd-sheet-${safeChartIdToKey(id)}`;
        const sheetEl = document.getElementById(sheetId);

        chartState[id] = { title, map: effectiveMap, activePipeline };

        if (charts[id]) {
            charts[id].destroy();
            delete charts[id];
        }

        if (sheetEl) {
            sheetEl.style.display = isTable ? 'block' : 'none';
            sheetEl.innerHTML = isTable ? renderSheetTable(ds.labels, ds.data, title) : '';
        }

        canvas.style.display = isTable ? 'none' : 'block';
        const emptyMsgId = `${id}-empty`;
        let emptyEl = document.getElementById(emptyMsgId);
        if (!emptyEl) {
            emptyEl = document.createElement('div');
            emptyEl.id = emptyMsgId;
            emptyEl.className = 'okd-chart-empty';
            canvas.insertAdjacentElement('afterend', emptyEl);
        }
        const isEmpty = ds.labels.length === 0;
        emptyEl.style.display = isEmpty ? 'block' : 'none';
        emptyEl.textContent = title.includes('Motivos de perda') ? 'Nenhum motivo de perda encontrado no período selecionado' : 'Sem dados no período selecionado';
        if (isEmpty) {
            canvas.style.display = 'none';
        }

        if (isTable) {
            return;
        }

        charts[id] = new Chart(canvas.getContext('2d'), {
            type: selectedType,
            data: {
                labels: ds.labels,
                datasets: [{
                    label: title,
                    data: ds.data,
                    backgroundColor: isLine ? 'rgba(37, 99, 235, 0.1)' : barColors,
                    borderColor: isLine ? '#2563eb' : barColors,
                    borderWidth: 2,
                    fill: isLine,
                    tension: 0.3,
                    pointRadius: isLine ? 3 : 0,
                    maxBarThickness: 36,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { mode: 'index', intersect: false },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#475467', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148, 163, 184, 0.2)' },
                        ticks: { color: '#667085', precision: 0 },
                    },
                },
            },
        });
    }

    function renderSheetTable(labels, data, title) {
        if (!labels.length) {
            const msg = title && title.includes('Motivos de perda') ? 'Nenhum motivo de perda encontrado no período selecionado' : 'Sem dados';
            return `<p>${escapeHtml(msg)}</p>`;
        }
        const rows = labels.map((label, idx) => `<tr><td>${escapeHtml(label)}</td><td>${escapeHtml(data[idx])}</td></tr>`).join('');
        return `<table class="okd-sheet-table"><thead><tr><th>Categoria</th><th>Quantidade</th></tr></thead><tbody>${rows || '<tr><td colspan="2">Sem dados</td></tr>'}</tbody></table>`;
    }

    function summarizeMap(map) {
        const entries = Object.entries(map || {});
        if (!entries.length) return 'Sem dados';
        return entries.slice(0, 2).map(([k, v]) => `${k}: ${v}`).join(' • ');
    }

    function cardHtml(title, value, subtitle) {
        const icon = CARD_ICONS[title] || '•';
        return `<article class="okd-card">
            <div class="okd-card-head"><span class="okd-card-icon">${icon}</span> ${escapeHtml(title)}</div>
            <div class="okd-card-value">${escapeHtml(value ?? 0)}</div>
            <div class="okd-card-sub">${escapeHtml(subtitle || '')}</div>
        </article>`;
    }

    function formatMinutes(minutes) {
        const value = Number(minutes || 0);
        if (!value) return '0 min';
        if (value < 60) return `${value} min`;
        if (value < 1440) return `${Math.floor(value / 60)}h ${value % 60}min`;
        const days = Math.floor(value / 1440);
        const hours = Math.floor((value % 1440) / 60);
        return `${days}d ${hours}h`;
    }

    function renderCards(cards) {
        const main = $('#okd-cards-main');
        const quality = $('#okd-cards-quality');
        const legacy = $('#okd-cards');
        if ((!main.length || !quality.length) && !legacy.length) return;
        const conversion = (Number(cards.periodo || 0) > 0) ? ((Number(cards.agendados || 0) / Number(cards.periodo || 1)) * 100).toFixed(2) + '%' : '0%';

        const mainItems = [
            cardHtml('Total de leads', cards.total, 'Base completa de leads sincronizados'),
            cardHtml('Leads no período', cards.periodo, 'Resultado conforme filtros aplicados'),
            cardHtml('Leads desqualificados', cards.desqualificados, 'Leads encerrados sem potencial'),
            cardHtml('Reuniões agendadas', cards.agendados, 'Status com reunião marcada'),
            cardHtml('Taxa de conversão', conversion, 'Reuniões / Leads no período'),
            cardHtml('Tempo médio até reunião', formatMinutes(cards.meeting_avg_minutes), 'Estimado por updated_at'),
        ];
        const qualityItems = [
            cardHtml('Desqualificados por faturamento', cards.desqualificados_faturamento, 'Não avançaram por baixa receita'),
            cardHtml('Base de recuperação', cards.base_recuperacao, 'Leads sem retorno/interação'),
            cardHtml('Não avançaram sem motivo', cards.sem_motivo_identificado, 'Sem razão identificada na Kommo'),
        ];
        if (main.length && quality.length) {
            main.html(mainItems.join(''));
            quality.html(qualityItems.join(''));
        } else if (legacy.length) {
            legacy.html(mainItems.concat(qualityItems).join(''));
        }
    }

    function renderMetricsDebug(debugData) {
        const el = $('#okd-debug-metrics');
        if (!el.length) return;
        if (!(typeof OptimizeKommoDashboard !== 'undefined' && OptimizeKommoDashboard.isAdmin)) {
            el.hide();
            $('#okd-toggle-debug').hide();
            return;
        }
        $('#okd-toggle-debug').show();
        const d = debugData || {};
        const rows = (d.sample_leads || []).map((r) => `<tr><td>${escapeHtml(r.lead_name)}</td><td>${escapeHtml(r.pipeline_name)}</td><td>${escapeHtml(r.status_name)}</td><td>${escapeHtml(r.faixa_faturamento)}</td><td>${escapeHtml(r.loss_reason_name)}</td><td>${escapeHtml(r.non_advance_category)}</td></tr>`).join('');
        el.html(`<h3>Debug de métricas</h3>
            <p>total de leads carregados na tabela: <strong>${escapeHtml(d.total_leads_filtrados ?? 0)}</strong></p>
            <p>total status_name = "NÃO AVANÇOU": <strong>${escapeHtml(d.total_nao_avancou ?? 0)}</strong></p>
            <p>total status_name = "Venda perdida": <strong>${escapeHtml(d.total_venda_perdida ?? 0)}</strong></p>
            <p>total com loss_reason_name preenchido: <strong>${escapeHtml(d.total_com_loss_reason ?? 0)}</strong></p>
            <p>total com non_advance_category preenchido: <strong>${escapeHtml(d.total_com_non_advance_category ?? 0)}</strong></p>
            <table class="okd-sheet-table"><thead><tr><th>Lead</th><th>Funil</th><th>Status</th><th>Faixa</th><th>Motivo</th><th>Categoria</th></tr></thead><tbody>${rows || '<tr><td colspan="6">Sem dados</td></tr>'}</tbody></table>`);
    }

    function renderTable(rows, activePipeline) {
        const tbody = $('#okd-table tbody');
        if (!tbody.length) return;

        let orderedRows = Array.isArray(rows) ? rows.slice() : [];
        if (isSdrPipeline(activePipeline)) {
            const statusIndex = {};
            SDR_STATUS_ORDER.forEach((name, idx) => {
                statusIndex[normalizeLabel(name)] = idx;
            });

            orderedRows = orderedRows.sort((a, b) => {
                const aIdx = statusIndex[normalizeLabel(a.status_name)];
                const bIdx = statusIndex[normalizeLabel(b.status_name)];
                const aRank = Number.isInteger(aIdx) ? aIdx : Number.MAX_SAFE_INTEGER;
                const bRank = Number.isInteger(bIdx) ? bIdx : Number.MAX_SAFE_INTEGER;
                if (aRank !== bRank) return aRank - bRank;
                return String(b.created_at || '').localeCompare(String(a.created_at || ''));
            });
        }

        const html = orderedRows.map((r) => {
            let link = '-';
            if (r.link_relatorio) {
                const safeHref = escapeHtml(r.link_relatorio);
                link = `<a href="${safeHref}" target="_blank" rel="noopener noreferrer">Abrir</a>`;
            }

            return `<tr><td>${escapeHtml(r.lead_name)}</td><td>${escapeHtml(r.created_at)}</td><td>${escapeHtml(r.responsible_user)}</td><td>${escapeHtml(r.pipeline_name)}</td><td>${escapeHtml(r.status_name)}</td><td>${escapeHtml(r.bu)}</td><td>${escapeHtml(r.origem)}</td><td>${escapeHtml(r.faixa_faturamento)}</td><td>${escapeHtml(r.loss_reason_name || '-')}</td><td>${escapeHtml(r.non_advance_category || '-')}</td><td>${link}</td></tr>`;
        }).join('');

        tbody.html(html || '<tr><td colspan="11">Sem dados.</td></tr>');
    }

    function dashboardPayload() {
        return {
            action: 'optimize_kommo_get_dashboard_data',
            nonce: OptimizeKommoDashboard.nonce,
            date_start: $('#okd-date-start').val(),
            date_end: $('#okd-date-end').val(),
            pipeline: $('#okd-pipeline').val(),
            status: $('#okd-status').val(),
            bu: $('#okd-bu').val(),
            origem: $('#okd-origem').val(),
            responsible_user: $('#okd-responsible').val(),
            faixa_faturamento: $('#okd-faixa').val(),
            loss_reason_name: $('#okd-loss-reason').val(),
            non_advance_category: $('#okd-non-advance-category').val(),
        };
    }

    function renderFilterSelect(selector, values, selectedValue) {
        const $select = $(selector);
        if (!$select.length) return;

        const current = selectedValue != null ? String(selectedValue) : String($select.val() || '');
        const options = ['<option value="">Todos</option>'];
        (values || []).forEach((value) => {
            const raw = String(value);
            const selected = raw === current ? ' selected' : '';
            options.push(`<option value="${escapeHtml(raw)}"${selected}>${escapeHtml(raw)}</option>`);
        });

        $select.html(options.join(''));
    }

    function renderFilterOptions(filterOptions, selectedFilters) {
        renderFilterSelect('#okd-pipeline', filterOptions.pipeline, selectedFilters.pipeline);
        renderFilterSelect('#okd-status', filterOptions.status, selectedFilters.status);
        renderFilterSelect('#okd-bu', filterOptions.bu, selectedFilters.bu);
        renderFilterSelect('#okd-origem', filterOptions.origem, selectedFilters.origem);
        renderFilterSelect('#okd-responsible', filterOptions.responsible_user, selectedFilters.responsible_user);
        renderFilterSelect('#okd-faixa', filterOptions.faixa_faturamento, selectedFilters.faixa_faturamento);
        renderFilterSelect('#okd-loss-reason', filterOptions.loss_reason_name, selectedFilters.loss_reason_name);
        renderFilterSelect('#okd-non-advance-category', filterOptions.non_advance_category, selectedFilters.non_advance_category);
    }

    function loadDashboard() {
        if (typeof OptimizeKommoDashboard === 'undefined') return;
        const payload = dashboardPayload();
        const pipelineFilter = payload.pipeline;

        $.post(OptimizeKommoDashboard.ajaxUrl, payload, function (resp) {
            if (!resp.success) return;
            renderFilterOptions(resp.data.filter_options || {}, payload);

            renderCards(resp.data.cards || {});
            renderMetricsDebug(resp.data.metricsDebug || {});
            renderTable(resp.data.table || [], pipelineFilter);

            renderChart('okd-chart-origem', 'Leads por origem', resp.data.charts.by_origem || {}, pipelineFilter);
            renderChart('okd-chart-funnel-main', 'Funil SDR', resp.data.charts.by_status || {}, pipelineFilter);
            renderChart('okd-chart-loss-reasons', 'Motivos de perda / não avanço', resp.data.lossReasonsChart || resp.data.charts.loss_reasons || {}, pipelineFilter);
            const meetingBuckets = resp.data.charts.meeting_time_buckets || {};
            renderChart('okd-chart-meeting-time', 'Tempo até reunião (faixas)', meetingBuckets, pipelineFilter);
        });
    }

    $(document).on('click', '#okd-apply-filters', function () {
        loadDashboard();
    });

    $(document).on('change', '.okd-chart-type', function () {
        const chartId = $(this).data('chart-id');
        if (!chartId || !chartState[chartId]) return;
        const state = chartState[chartId];
        renderChart(chartId, state.title, state.map, state.activePipeline);
    });

    $(document).on('click', '#okd-toggle-debug', function () {
        $('#okd-debug-metrics').toggle();
    });

    $(document).on('click', '#optimize-kommo-sync-now', function () {
        if (typeof OptimizeKommoAdmin === 'undefined') return;

        $('#optimize-kommo-sync-feedback').text('Sincronizando...');
        $.post(OptimizeKommoAdmin.ajaxUrl, {
            action: 'optimize_kommo_sync_now',
            nonce: OptimizeKommoAdmin.nonce,
        }, function (resp) {
            if (resp.success) {
                $('#optimize-kommo-sync-feedback').text('Sincronização concluída.');
            } else {
                $('#optimize-kommo-sync-feedback').text(resp.data && resp.data.message ? resp.data.message : 'Erro na sincronização.');
            }
        });
    });

    $(document).ready(function () {
        loadDashboard();
    });
})(jQuery);
