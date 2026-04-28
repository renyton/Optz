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

## 2) Configuração de subdomínio e token da Kommo

1. No WordPress, acesse o menu **Optimize Kommo**.
2. Preencha os campos:
   - **Subdomínio Kommo**: somente o subdomínio (ex.: `minhaempresa`), sem `https://`.
   - **Token privado**: token de API da Kommo com permissões de leitura para leads, usuários e pipelines.
   - **Intervalo de atualização**: em minutos (recomendado: `10`).
   - **Usuários autorizados**: IDs de usuários WordPress separados por vírgula (opcional).
3. Clique em **Salvar configurações**.

### Onde gerar o token na Kommo

No painel da Kommo, gere um token privado para integração da API v4 e conceda escopos mínimos necessários para leitura de:
- Leads
- Usuários
- Pipelines/Statuses

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

### Erro: “Subdomínio ou token da Kommo não configurado”
**Causa:** campos em branco nas configurações.
**Solução:** preencher subdomínio e token no menu **Optimize Kommo** e salvar.

### Erro de API 401/403
**Causa:** token inválido, expirado ou sem escopo.
**Solução:** gerar novo token privado na Kommo com permissões corretas.

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

### Sincronização manual retorna erro
**Causa:** falha de conexão com API, timeout, credenciais inválidas ou problema de banco.
**Solução:** consultar **Logs recentes** e corrigir a causa (token, conectividade, permissões, etc.).

---

## Boas práticas

- Mantenha o token da Kommo apenas no admin (não exponha no front-end).
- Use HTTPS no WordPress e na Kommo.
- Faça backup antes de atualizações.
- Revise periodicamente os logs de sincronização.
