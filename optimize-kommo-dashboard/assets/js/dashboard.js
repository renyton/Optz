(function ($) {
    const charts = {};
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
    };

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

    function chartKindById(id) {
        if (id.includes('origem')) return 'origem';
        if (id.includes('status')) return 'status';
        if (id.includes('faixa')) return 'faixa';
        if (id.includes('pipeline')) return 'pipeline';
        if (id.includes('bu')) return 'bu';
        return 'day';
    }

    function renderChart(id, title, map) {
        const canvas = document.getElementById(id);
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        const ds = toDataset(map);
        const kind = chartKindById(id);
        const palette = CHART_COLORS[kind] || CHART_COLORS.day;
        const isLine = id === 'okd-chart-day';
        const barColors = ds.labels.map((_, i) => palette[i % palette.length]);

        if (charts[id]) {
            charts[id].destroy();
        }

        charts[id] = new Chart(canvas.getContext('2d'), {
            type: isLine ? 'line' : 'bar',
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

    function summarizeMap(map) {
        const entries = Object.entries(map || {});
        if (!entries.length) return 'Sem dados';
        return entries.slice(0, 2).map(([k, v]) => `${k}: ${v}`).join(' • ');
    }

    function cardHtml(title, value, subtitle) {
        const icon = CARD_ICONS[title] || '•';
        return `<article class="okd-card">
            <div class="okd-card-head"><span class="okd-card-icon">${icon}</span> ${escapeHtml(title)}</div>
            <div class="okd-card-value">${escapeHtml(value || 0)}</div>
            <div class="okd-card-sub">${escapeHtml(subtitle || '')}</div>
        </article>`;
    }

    function renderCards(cards) {
        const el = $('#okd-cards');
        if (!el.length) return;

        const items = [
            cardHtml('Total de leads', cards.total, 'Base completa de leads sincronizados'),
            cardHtml('Leads no período', cards.periodo, 'Resultado conforme filtros aplicados'),
            cardHtml('Leads qualificados', cards.qualificados, 'Leads prontos para avanço comercial'),
            cardHtml('Leads desqualificados', cards.desqualificados, 'Leads encerrados sem potencial'),
            cardHtml('Reuniões agendadas', cards.agendados, 'Status com reunião marcada'),
            cardHtml('Acima de R$ 20M/ano', cards.acima_20m, 'Leads HIGH VALUE'),
            cardHtml('Leads por BU', Object.keys(cards.por_bu || {}).length, summarizeMap(cards.por_bu)),
            cardHtml('Leads por origem', Object.keys(cards.por_origem || {}).length, summarizeMap(cards.por_origem)),
        ];

        el.html(items.join(''));
    }

    function renderTable(rows) {
        const tbody = $('#okd-table tbody');
        if (!tbody.length) return;

        const html = (rows || []).map((r) => {
            let link = '-';
            if (r.link_relatorio) {
                const safeHref = escapeHtml(r.link_relatorio);
                link = `<a href="${safeHref}" target="_blank" rel="noopener noreferrer">Abrir</a>`;
            }

            return `<tr><td>${escapeHtml(r.lead_name)}</td><td>${escapeHtml(r.created_at)}</td><td>${escapeHtml(r.responsible_user)}</td><td>${escapeHtml(r.pipeline_name)}</td><td>${escapeHtml(r.status_name)}</td><td>${escapeHtml(r.bu)}</td><td>${escapeHtml(r.origem)}</td><td>${escapeHtml(r.faixa_faturamento)}</td><td>${link}</td></tr>`;
        }).join('');

        tbody.html(html || '<tr><td colspan="9">Sem dados.</td></tr>');
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
        };
    }

    function loadDashboard() {
        if (typeof OptimizeKommoDashboard === 'undefined') return;

        $.post(OptimizeKommoDashboard.ajaxUrl, dashboardPayload(), function (resp) {
            if (!resp.success) return;

            renderCards(resp.data.cards || {});
            renderTable(resp.data.table || []);

            renderChart('okd-chart-day', 'Leads por dia', resp.data.charts.by_day || {});
            renderChart('okd-chart-origem', 'Leads por origem', resp.data.charts.by_origem || {});
            renderChart('okd-chart-bu', 'Leads por BU', resp.data.charts.by_bu || {});
            renderChart('okd-chart-faixa', 'Leads por faturamento', resp.data.charts.by_faixa || {});
            renderChart('okd-chart-status', 'Leads por status', resp.data.charts.by_status || {});
            renderChart('okd-chart-pipeline', 'Leads por funil', resp.data.charts.by_pipeline || {});
        });
    }

    $(document).on('click', '#okd-apply-filters', function () {
        loadDashboard();
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
