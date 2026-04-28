(function ($) {
    const charts = {};

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

    function renderChart(id, title, map) {
        const canvas = document.getElementById(id);
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        const ds = toDataset(map);
        if (charts[id]) {
            charts[id].destroy();
        }

        charts[id] = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ds.labels,
                datasets: [{ label: title, data: ds.data }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                },
            },
        });
    }

    function renderCards(cards) {
        const el = $('#okd-cards');
        if (!el.length) return;

        const basic = {
            'Total de leads': cards.total,
            'Leads no período': cards.periodo,
            'Leads qualificados': cards.qualificados,
            'Leads desqualificados': cards.desqualificados,
            'Reuniões agendadas': cards.agendados,
            'Acima de R$ 20M/ano': cards.acima_20m,
        };

        const items = Object.entries(basic).map(([k, v]) => `<div class="okd-card"><strong>${escapeHtml(k)}</strong><span>${escapeHtml(v || 0)}</span></div>`);
        items.push(`<div class="okd-card"><strong>Leads por BU</strong><span>${escapeHtml(JSON.stringify(cards.por_bu || {}))}</span></div>`);
        items.push(`<div class="okd-card"><strong>Leads por origem</strong><span>${escapeHtml(JSON.stringify(cards.por_origem || {}))}</span></div>`);

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
