<?php if (! defined('ABSPATH')) { exit; } ?>
<div id="optimize-kommo-dashboard">
    <div class="okd-filters">
        <input type="date" id="okd-date-start" />
        <input type="date" id="okd-date-end" />
        <input type="text" id="okd-pipeline" placeholder="Funil" />
        <input type="text" id="okd-status" placeholder="Status" />
        <input type="text" id="okd-bu" placeholder="BU" />
        <input type="text" id="okd-origem" placeholder="Origem" />
        <input type="text" id="okd-responsible" placeholder="Responsável" />
        <input type="text" id="okd-faixa" placeholder="Faixa faturamento" />
        <button id="okd-apply-filters">Filtrar</button>
    </div>

    <div class="okd-cards" id="okd-cards"></div>

    <div class="okd-charts">
        <canvas id="okd-chart-day"></canvas>
        <canvas id="okd-chart-origem"></canvas>
        <canvas id="okd-chart-bu"></canvas>
        <canvas id="okd-chart-faixa"></canvas>
        <canvas id="okd-chart-status"></canvas>
        <canvas id="okd-chart-pipeline"></canvas>
    </div>

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
