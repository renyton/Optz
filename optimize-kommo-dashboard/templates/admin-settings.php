<?php if (! defined('ABSPATH')) { exit; } ?>
<div class="wrap">
    <h1><?php esc_html_e('Optimize Kommo Dashboard', 'optimize-kommo-dashboard'); ?></h1>

    <?php if (! empty($oauth_notice)) : ?>
        <div class="notice notice-<?php echo esc_attr($oauth_notice['class']); ?> is-dismissible">
            <p><?php echo esc_html($oauth_notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields('optimize_kommo_settings'); ?>
        <table class="form-table">
            <tr>
                <th><label for="optimize_kommo_subdomain">Subdomínio Kommo</label></th>
                <td><input type="text" id="optimize_kommo_subdomain" name="optimize_kommo_subdomain" value="<?php echo esc_attr(get_option('optimize_kommo_subdomain', '')); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="optimize_kommo_client_id">Client ID (OAuth)</label></th>
                <td><input type="text" id="optimize_kommo_client_id" name="optimize_kommo_client_id" value="<?php echo esc_attr(get_option('optimize_kommo_client_id', '')); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="optimize_kommo_client_secret">Client Secret (OAuth)</label></th>
                <td><input type="password" id="optimize_kommo_client_secret" name="optimize_kommo_client_secret" value="<?php echo esc_attr(get_option('optimize_kommo_client_secret', '')); ?>" class="regular-text" autocomplete="new-password" /></td>
            </tr>
            <tr>
                <th>Redirect URI</th>
                <td><code><?php echo esc_html(Optimize_Kommo_API::get_redirect_uri()); ?></code></td>
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
                <th>Status OAuth</th>
                <td>
                    <?php if (! empty($oauth_status['connected'])) : ?>
                        <span style="color:#0a7f35;font-weight:600;"><?php esc_html_e('Conectado', 'optimize-kommo-dashboard'); ?></span>
                        <?php if (! empty($oauth_status['connected_at'])) : ?>
                            <br />
                            <small><?php echo esc_html(sprintf(__('Conectado em: %s', 'optimize-kommo-dashboard'), $oauth_status['connected_at'])); ?></small>
                        <?php endif; ?>
                    <?php else : ?>
                        <span style="color:#b32d2e;font-weight:600;"><?php esc_html_e('Desconectado', 'optimize-kommo-dashboard'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Última sincronização</th>
                <td><?php echo esc_html((string) $last_sync); ?></td>
            </tr>
        </table>

        <?php submit_button(__('Salvar configurações', 'optimize-kommo-dashboard')); ?>
    </form>

    <p>
        <?php if (is_wp_error($oauth_authorize_url)) : ?>
            <button type="button" class="button" disabled><?php esc_html_e('Conectar com Kommo', 'optimize-kommo-dashboard'); ?></button>
            <span><?php echo esc_html($oauth_authorize_url->get_error_message()); ?></span>
        <?php else : ?>
            <a class="button button-secondary" href="<?php echo esc_url($oauth_authorize_url); ?>"><?php esc_html_e('Conectar com Kommo', 'optimize-kommo-dashboard'); ?></a>
        <?php endif; ?>

        <?php if (! empty($oauth_status['connected'])) : ?>
            <a class="button" href="<?php echo esc_url($disconnect_url); ?>" style="margin-left:8px;"><?php esc_html_e('Desconectar', 'optimize-kommo-dashboard'); ?></a>
        <?php endif; ?>
    </p>

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
