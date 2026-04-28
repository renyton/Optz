<?php if (! defined('ABSPATH')) { exit; } ?>
<div class="wrap">
    <h1><?php esc_html_e('Optimize Kommo Dashboard', 'optimize-kommo-dashboard'); ?></h1>

    <?php if (! empty($_GET['oauth_error'])) : ?>
        <div class="notice notice-error"><p><?php echo esc_html('OAuth erro: ' . sanitize_text_field((string) $_GET['oauth_error'])); ?></p></div>
    <?php endif; ?>
    <?php if (! empty($_GET['oauth_success'])) : ?>
        <div class="notice notice-success"><p><?php esc_html_e('OAuth conectado com sucesso.', 'optimize-kommo-dashboard'); ?></p></div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields('optimize_kommo_settings'); ?>
        <table class="form-table">
            <tr>
                <th><label for="optimize_kommo_subdomain">Subdomínio Kommo</label></th>
                <td><input type="text" id="optimize_kommo_subdomain" name="optimize_kommo_subdomain" value="<?php echo esc_attr(get_option('optimize_kommo_subdomain', '')); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="optimize_kommo_client_id">OAuth Client ID</label></th>
                <td><input type="text" id="optimize_kommo_client_id" name="optimize_kommo_client_id" value="<?php echo esc_attr(get_option('optimize_kommo_client_id', '')); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="optimize_kommo_client_secret">OAuth Client Secret</label></th>
                <td><input type="password" id="optimize_kommo_client_secret" name="optimize_kommo_client_secret" value="<?php echo esc_attr(get_option('optimize_kommo_client_secret', '')); ?>" class="regular-text" autocomplete="new-password" /></td>
            </tr>
            <tr>
                <th><label for="optimize_kommo_redirect_uri">Redirect URI (exato do app Kommo)</label></th>
                <td>
                    <input type="text" id="optimize_kommo_redirect_uri" name="optimize_kommo_redirect_uri" value="<?php echo esc_attr(get_option('optimize_kommo_redirect_uri', '')); ?>" class="regular-text code" />
                    <p class="description">Sugestão de callback deste plugin: <code><?php echo esc_html((string) $oauth_callback); ?></code></p>
                </td>
            </tr>
            <tr>
                <th><label for="optimize_kommo_token">Token privado</label></th>
                <td><input type="password" id="optimize_kommo_token" name="optimize_kommo_token" value="<?php echo esc_attr(get_option('optimize_kommo_token', '')); ?>" class="regular-text" autocomplete="new-password" /></td>
            </tr>
            <tr>
                <th><label for="optimize_kommo_refresh_token">Refresh token</label></th>
                <td><input type="password" id="optimize_kommo_refresh_token" name="optimize_kommo_refresh_token" value="<?php echo esc_attr(get_option('optimize_kommo_refresh_token', '')); ?>" class="regular-text" autocomplete="new-password" /></td>
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
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="optimize_kommo_oauth_start" />
            <?php wp_nonce_field('optimize_kommo_oauth_start'); ?>
            <button type="submit" class="button button-secondary"><?php esc_html_e('Conectar com OAuth Kommo', 'optimize-kommo-dashboard'); ?></button>
        </form>
    </p>

    <?php if (is_array($oauth_debug) && ! empty($oauth_debug)) : ?>
        <h2><?php esc_html_e('Debug OAuth', 'optimize-kommo-dashboard'); ?></h2>
        <table class="widefat striped">
            <tbody>
                <tr><th>Última etapa</th><td><code><?php echo esc_html((string) ($oauth_debug['last_step'] ?? '')); ?></code></td></tr>
                <tr><th>OAuth URL completa</th><td><code style="word-break: break-all;"><?php echo esc_html((string) ($oauth_debug['oauth_url'] ?? '')); ?></code></td></tr>
                <tr><th>redirect_uri bruto</th><td><code><?php echo esc_html((string) ($oauth_debug['redirect_uri_raw'] ?? '')); ?></code></td></tr>
                <tr><th>redirect_uri enviado</th><td><code><?php echo esc_html((string) ($oauth_debug['redirect_uri_sent'] ?? '')); ?></code></td></tr>
                <tr><th>Token endpoint</th><td><code><?php echo esc_html((string) ($oauth_debug['token_endpoint'] ?? '')); ?></code></td></tr>
                <tr><th>Atualizado em</th><td><?php echo esc_html((string) ($oauth_debug['updated_at'] ?? '')); ?></td></tr>
            </tbody>
        </table>
    <?php endif; ?>

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
