<?php if (! defined('ABSPATH')) { exit; } ?>
<div id="optimize-kommo-dashboard">
    <div class="okd-dashboard-header okd-surface">
        <div class="okd-brand">
            <?php $logo_url = (string) get_option('optimize_kommo_dashboard_logo_url', ''); ?>
            <?php if ('' !== $logo_url) : ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="Grupo Optimize" class="okd-brand-logo" />
            <?php endif; ?>
            <h1 class="okd-brand-title"><?php echo esc_html((string) get_option('optimize_kommo_dashboard_title', 'Dashboard Comercial – Grupo Optimize')); ?></h1>
        </div>
    </div>
    <div class="okd-topbar">
        <button type="button" id="okd-theme-toggle" class="button">Modo escuro</button>
        <a class="okd-btn-logout" href="<?php echo esc_url(wp_logout_url(Optimize_Kommo_Dashboard::get_login_page_url())); ?>">Sair</a>
    </div>
    <div class="okd-surface okd-filters-bar okd-grid-full">
        <div class="okd-filter">
            <label for="okd-date-start">Data inicial</label>
            <input type="date" id="okd-date-start" />
        </div>
        <div class="okd-filter">
            <label for="okd-date-end">Data final</label>
            <input type="date" id="okd-date-end" />
        </div>
        <div class="okd-filter">
            <label for="okd-pipeline">Funil</label>
            <select id="okd-pipeline"><option value="SDR | Grupo Optimize">SDR | Grupo Optimize</option></select>
        </div>
        <div class="okd-filter">
            <label for="okd-status">Status</label>
            <select id="okd-status"><option value="">Todos</option></select>
        </div>
        <div class="okd-filter">
            <label for="okd-bu">BU</label>
            <select id="okd-bu"><option value="">Todos</option></select>
        </div>
        <div class="okd-filter">
            <label for="okd-origem">Origem</label>
            <select id="okd-origem"><option value="">Todos</option></select>
        </div>
        <div class="okd-filter">
            <label for="okd-responsible">Responsável</label>
            <select id="okd-responsible"><option value="">Todos</option></select>
        </div>
        <div class="okd-filter">
            <label for="okd-faixa">Faixa de faturamento</label>
            <select id="okd-faixa"><option value="">Todos</option></select>
        </div>
        <div class="okd-filter">
            <label for="okd-loss-reason">Motivo de perda</label>
            <select id="okd-loss-reason"><option value="">Todos</option></select>
        </div>
        <div class="okd-filter">
            <label for="okd-non-advance-category">Categoria de não avanço</label>
            <select id="okd-non-advance-category"><option value="">Todos</option></select>
        </div>
        <button id="okd-apply-filters" class="okd-btn-primary">Aplicar filtros</button>
    </div>

    <section class="okd-kpi-section okd-grid-full">
        <h2>SEÇÃO 1 – KPIs principais</h2>
        <div class="okd-cards okd-cards-main" id="okd-cards-main"></div>
    </section>
    <section class="okd-kpi-section okd-grid-full">
        <h2>SEÇÃO 2 – Qualidade do funil</h2>
        <div class="okd-cards okd-cards-quality" id="okd-cards-quality"></div>
    </section>
    <div class="okd-debug-toggle-wrap"><button type="button" id="okd-toggle-debug" class="button">Modo debug</button></div>
    <div class="okd-surface okd-debug-metrics" id="okd-debug-metrics"></div>

    <div class="okd-charts okd-grid-full">
        <section class="okd-surface okd-chart-card okd-chart-funnel">
            <div class="okd-chart-head"><h3>SEÇÃO 3 – Funil SDR | Grupo Optimize</h3><select class="okd-chart-type" data-chart-id="okd-chart-funnel-main"><option value="bar">Barra</option><option value="line">Linha</option><option value="table">Planilha</option></select></div>
            <canvas id="okd-chart-funnel-main"></canvas><div id="okd-sheet-funnel-main" class="okd-chart-sheet"></div>
        </section>
        <section class="okd-surface okd-chart-card">
            <div class="okd-chart-head"><h3>SEÇÃO 4 – Motivos de perda</h3><select class="okd-chart-type" data-chart-id="okd-chart-loss-reasons"><option value="bar">Barra</option><option value="pie">Pizza</option><option value="doughnut">Rosca</option><option value="table">Planilha</option></select></div>
            <canvas id="okd-chart-loss-reasons"></canvas><div id="okd-sheet-loss-reasons" class="okd-chart-sheet"></div>
        </section>
        <section class="okd-surface okd-chart-card">
            <div class="okd-chart-head"><h3>SEÇÃO 4 – Tempo até reunião (faixas)</h3><select class="okd-chart-type" data-chart-id="okd-chart-meeting-time"><option value="bar">Barra</option><option value="table">Planilha</option></select></div>
            <canvas id="okd-chart-meeting-time"></canvas><div id="okd-sheet-meeting-time" class="okd-chart-sheet"></div>
        </section>
        <section class="okd-surface okd-chart-card">
            <div class="okd-chart-head"><h3>SEÇÃO 4 – Origem</h3><select class="okd-chart-type" data-chart-id="okd-chart-origem"><option value="bar">Barra</option><option value="pie">Pizza</option><option value="doughnut">Rosca</option><option value="table">Planilha</option></select></div>
            <canvas id="okd-chart-origem"></canvas><div id="okd-sheet-origem" class="okd-chart-sheet"></div>
        </section>
    </div>

    <h2 class="okd-grid-full">SEÇÃO 5 – Execução</h2>
    <div class="okd-surface okd-table-wrap okd-grid-full">
        <table class="okd-table" id="okd-table">
            <thead>
                <tr>
                    <th>Lead</th>
                    <th>Data criação</th>
                    <th>Responsável</th>
                    <th>Funil</th>
                    <th>Status</th>
                    <th>BU</th>
                    <th>Origem</th>
                    <th>Faixa faturamento</th>
                    <th>Motivo de perda</th>
                    <th>Categoria de não avanço</th>
                    <th>Relatório</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
