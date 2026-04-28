<?php if (! defined('ABSPATH')) { exit; } ?>
<div id="optimize-kommo-dashboard">
    <div class="okd-surface okd-filters-bar">
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
            <select id="okd-pipeline"><option value="">Todos</option></select>
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
        <button id="okd-apply-filters" class="okd-btn-primary">Aplicar filtros</button>
    </div>

    <div class="okd-cards" id="okd-cards"></div>

    <div class="okd-charts">
        <section class="okd-surface okd-chart-card">
            <div class="okd-chart-head"><h3>Leads por dia</h3><select class="okd-chart-type" data-chart-id="okd-chart-day"><option value="bar">Barra</option><option value="line">Linha</option><option value="pie">Pizza</option><option value="doughnut">Rosca</option><option value="table">Planilha</option></select></div>
            <canvas id="okd-chart-day"></canvas><div id="okd-sheet-day" class="okd-chart-sheet"></div>
        </section>
        <section class="okd-surface okd-chart-card">
            <div class="okd-chart-head"><h3>Leads por origem</h3><select class="okd-chart-type" data-chart-id="okd-chart-origem"><option value="bar">Barra</option><option value="line">Linha</option><option value="pie">Pizza</option><option value="doughnut">Rosca</option><option value="table">Planilha</option></select></div>
            <canvas id="okd-chart-origem"></canvas><div id="okd-sheet-origem" class="okd-chart-sheet"></div>
        </section>
        <section class="okd-surface okd-chart-card">
            <div class="okd-chart-head"><h3>Leads por BU</h3><select class="okd-chart-type" data-chart-id="okd-chart-bu"><option value="bar">Barra</option><option value="line">Linha</option><option value="pie">Pizza</option><option value="doughnut">Rosca</option><option value="table">Planilha</option></select></div>
            <canvas id="okd-chart-bu"></canvas><div id="okd-sheet-bu" class="okd-chart-sheet"></div>
        </section>
        <section class="okd-surface okd-chart-card">
            <div class="okd-chart-head"><h3>Leads por faturamento</h3><select class="okd-chart-type" data-chart-id="okd-chart-faixa"><option value="bar">Barra</option><option value="line">Linha</option><option value="pie">Pizza</option><option value="doughnut">Rosca</option><option value="table">Planilha</option></select></div>
            <canvas id="okd-chart-faixa"></canvas><div id="okd-sheet-faixa" class="okd-chart-sheet"></div>
        </section>
        <section class="okd-surface okd-chart-card">
            <div class="okd-chart-head"><h3>Leads por status</h3><select class="okd-chart-type" data-chart-id="okd-chart-status"><option value="bar">Barra</option><option value="line">Linha</option><option value="pie">Pizza</option><option value="doughnut">Rosca</option><option value="table">Planilha</option></select></div>
            <canvas id="okd-chart-status"></canvas><div id="okd-sheet-status" class="okd-chart-sheet"></div>
        </section>
        <section class="okd-surface okd-chart-card">
            <div class="okd-chart-head"><h3>Leads por funil</h3><select class="okd-chart-type" data-chart-id="okd-chart-pipeline"><option value="bar">Barra</option><option value="line">Linha</option><option value="pie">Pizza</option><option value="doughnut">Rosca</option><option value="table">Planilha</option></select></div>
            <canvas id="okd-chart-pipeline"></canvas><div id="okd-sheet-pipeline" class="okd-chart-sheet"></div>
        </section>
    </div>

    <div class="okd-surface okd-table-wrap">
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
                    <th>Relatório</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
