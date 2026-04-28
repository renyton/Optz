<?php if (! defined('ABSPATH')) { exit; } ?>
<div class="wrap">
    <h1><?php esc_html_e('Optimize Kommo Dashboard', 'optimize-kommo-dashboard'); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('optimize_kommo_settings'); ?>
        <table class="form-table">
            <tr>
                <th><label for="optimize_kommo_subdomain">Subdomínio Kommo</label></th>
                <td><input type="text" id="optimize_kommo_subdomain" name="optimize_kommo_subdomain" value="<?php echo esc_attr(get_option('optimize_kommo_subdomain', '')); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="optimize_kommo_token">Token privado</label></th>
                <td><input type="password" id="optimize_kommo_token" name="optimize_kommo_token" value="<?php echo esc_attr(get_option('optimize_kommo_token', '')); ?>" class="regular-text" autocomplete="new-password" /></td>
            </tr>
            <tr>
                <th><label for="optimize_kommo_interval">Intervalo de atualização (minutos)</label></th>
                <td><input type="number" min="1" id="optimize_kommo_interval" name="optimize_kommo_interval" value="<?php echo esc_attr((string) get_option('optimize_kommo_interval', 10)); ?>" class="small-text" /></td>
            </tr>
            <tr>
                <th><label for="optimize_kommo_authorized_users">Usuários autorizados (IDs separados por vírgula)</label></th>
                <td><input type="text" id="optimize_kommo_authorized_users" name="optimize_kommo_authorized_users" value="<?php echo esc_attr(get_option('optimize_kommo_authorized_users', '')); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th>Última sincronização</th>
                <td><?php echo esc_html((string) $last_sync); ?></td>
            </tr>
        </table>

        <?php submit_button(__('Salvar configurações', 'optimize-kommo-dashboard')); ?>
    </form>

    <p>
        <button type="button" class="button button-primary" id="optimize-kommo-sync-now"><?php esc_html_e('Sincronizar agora', 'optimize-kommo-dashboard'); ?></button>
        <span id="optimize-kommo-sync-feedback"></span>
    </p>

    <h2><?php esc_html_e('Logs recentes', 'optimize-kommo-dashboard'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Início</th>
                <th>Fim</th>
                <th>Status</th>
                <th>Fetched</th>
                <th>Criados</th>
                <th>Atualizados</th>
                <th>Erro</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)) : ?>
                <tr><td colspan="8">Sem logs.</td></tr>
            <?php else : foreach ($logs as $log) : ?>
                <tr>
                    <td><?php echo esc_html((string) $log['id']); ?></td>
                    <td><?php echo esc_html((string) $log['sync_started_at']); ?></td>
                    <td><?php echo esc_html((string) $log['sync_finished_at']); ?></td>
                    <td><?php echo esc_html((string) $log['status']); ?></td>
                    <td><?php echo esc_html((string) $log['total_fetched']); ?></td>
                    <td><?php echo esc_html((string) $log['total_created']); ?></td>
                    <td><?php echo esc_html((string) $log['total_updated']); ?></td>
                    <td><?php echo esc_html((string) $log['error_message']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
