# Optimize Kommo Dashboard

Plugin WordPress para sincronizar leads da Kommo e exibir um dashboard interno com filtros, cards, gráficos e tabela.

## 1) Instalação no WordPress

1. Copie a pasta `optimize-kommo-dashboard` para:
   - `wp-content/plugins/optimize-kommo-dashboard`
2. No painel WordPress, acesse **Plugins > Plugins instalados**.
3. Ative o plugin **Optimize Kommo Dashboard**.
4. Após ativar, o plugin cria automaticamente as tabelas:
   - `wp_optimize_kommo_leads`
   - `wp_optimize_kommo_sync_logs`

> Observação: se seu prefixo não for `wp_`, o plugin usa automaticamente o prefixo configurado no WordPress.

---

## 2) Configuração do subdomínio e OAuth 2.0 da Kommo

1. No WordPress, acesse o menu **Optimize Kommo**.
2. Preencha os campos:
   - **Subdomínio Kommo**: somente o subdomínio (ex.: `minhaempresa`), sem `https://`.
   - **Client ID (OAuth)** e **Client Secret (OAuth)**: credenciais da integração OAuth criada na Kommo.
   - **Intervalo de atualização**: em minutos (recomendado: `10`).
   - **Usuários autorizados**: IDs de usuários WordPress separados por vírgula (opcional).
3. Clique em **Salvar configurações**.
4. Na Kommo, cadastre no app OAuth a mesma **Redirect URI** exibida na tela do plugin.
5. Clique em **Conectar com Kommo** e autorize a integração.

### Fluxo OAuth implementado

- O plugin recebe o `authorization_code` no callback admin.
- Troca o código por `access_token` e `refresh_token`.
- Salva tokens com expiração em `wp_options`.
- Renova automaticamente com `refresh_token` quando necessário.

---

## 3) Como usar o shortcode

1. Crie ou edite uma página no WordPress.
2. Adicione o shortcode:

```txt
[optimize_kommo_dashboard]
```

3. Publique a página.

### Regras de acesso

O dashboard só é exibido para:
- usuários logados com `manage_options` (administradores), ou
- usuários listados em **Usuários autorizados** na configuração do plugin.

---

## 4) Como executar sincronização manual

1. Vá para **Optimize Kommo** no admin do WordPress.
2. Clique no botão **Sincronizar agora**.
3. Aguarde o retorno de sucesso/erro.
4. Confira:
   - **Última sincronização**
   - **Logs recentes** (status, total buscado, criados, atualizados e erro)

---

## 5) Como validar se o WP-Cron está funcionando

### Validação rápida no admin

- Verifique se a **Última sincronização** é atualizada periodicamente sem clicar em “Sincronizar agora”.
- Verifique se novos registros aparecem em **Logs recentes**.

### Validação técnica (recomendado)

- Instale o plugin **WP Crontrol** e confirme o evento:
  - Hook: `optimize_kommo_sync_event`
  - Recorrência: `optimize_kommo_interval`

### Em produção

Em sites com baixo tráfego, prefira cron real no servidor chamando `wp-cron.php` para garantir execução.

Exemplo (Linux crontab):

```bash
*/5 * * * * curl -s https://seu-dominio.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

---

## 6) Erros comuns e como resolver

### Erro: “Subdomínio, Client ID ou Client Secret da Kommo não configurados”
**Causa:** campos OAuth em branco.
**Solução:** preencher subdomínio, Client ID e Client Secret, salvar e conectar novamente.

### Erro OAuth no callback / state inválido
**Causa:** sessão antiga de autorização, callback incorreto ou tentativa de replay.
**Solução:** conferir Redirect URI cadastrada na Kommo e clicar novamente em **Conectar com Kommo**.

### Erro de API 401/403
**Causa:** autorização removida, refresh token inválido ou app sem escopo.
**Solução:** desconectar e conectar novamente; revisar permissões do app OAuth na Kommo.

### Dashboard vazio
**Causa:** ainda não houve sincronização ou filtros muito restritivos.
**Solução:**
1. Clique em **Sincronizar agora**.
2. Limpe os filtros e recarregue a página.
3. Verifique logs de erro no admin.

### WP-Cron não executa automaticamente
**Causa:** baixo tráfego ou cron desativado no WordPress (`DISABLE_WP_CRON`).
**Solução:** configurar cron do servidor para chamar `wp-cron.php` periodicamente.

### Sem permissão para ver dashboard
**Causa:** usuário não é administrador e não está na lista de autorizados.
**Solução:** adicionar o ID do usuário em **Usuários autorizados**.

---

## Boas práticas

- Nunca exponha `client_secret`, `access_token` ou `refresh_token` no front-end.
- Use HTTPS no WordPress e na Kommo.
- Faça backup antes de atualizações.
- Revise periodicamente os logs de sincronização.
