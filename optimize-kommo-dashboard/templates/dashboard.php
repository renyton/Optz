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
            <input type="text" id="okd-pipeline" placeholder="Ex.: Comercial" />
        </div>
        <div class="okd-filter">
            <label for="okd-status">Status</label>
            <input type="text" id="okd-status" placeholder="Ex.: Agendado" />
        </div>
        <div class="okd-filter">
            <label for="okd-bu">BU</label>
            <input type="text" id="okd-bu" placeholder="Ex.: Consulting" />
        </div>
        <div class="okd-filter">
            <label for="okd-origem">Origem</label>
            <input type="text" id="okd-origem" placeholder="Ex.: Diagnóstico Online" />
        </div>
        <div class="okd-filter">
            <label for="okd-responsible">Responsável</label>
            <input type="text" id="okd-responsible" placeholder="Ex.: SDR Ana" />
        </div>
        <div class="okd-filter">
            <label for="okd-faixa">Faixa de faturamento</label>
            <input type="text" id="okd-faixa" placeholder="Ex.: 10M - 20M" />
        </div>
        <button id="okd-apply-filters" class="okd-btn-primary">Aplicar filtros</button>
    </div>

    <div class="okd-cards" id="okd-cards"></div>

    <div class="okd-charts">
        <section class="okd-surface okd-chart-card"><h3>Leads por dia</h3><canvas id="okd-chart-day"></canvas></section>
        <section class="okd-surface okd-chart-card"><h3>Leads por origem</h3><canvas id="okd-chart-origem"></canvas></section>
        <section class="okd-surface okd-chart-card"><h3>Leads por BU</h3><canvas id="okd-chart-bu"></canvas></section>
        <section class="okd-surface okd-chart-card"><h3>Leads por faturamento</h3><canvas id="okd-chart-faixa"></canvas></section>
        <section class="okd-surface okd-chart-card"><h3>Leads por status</h3><canvas id="okd-chart-status"></canvas></section>
        <section class="okd-surface okd-chart-card"><h3>Leads por funil</h3><canvas id="okd-chart-pipeline"></canvas></section>
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
