# Diagnóstico do Worker - Sistema de Monitoramento CKAN

## Problema Identificado

O sistema de monitoramento está com o worker paralisado no estado "pending", impedindo que as análises sejam processadas. Este documento fornece uma solução completa para diagnosticar e resolver o problema.

## Arquivos de Diagnóstico Criados

### 1. `diagnostico-worker.php`
Script completo de diagnóstico que verifica:
- Ambiente PHP e extensões necessárias
- Estrutura de arquivos e permissões
- Arquivo de lock e dados
- Arquivo de PID e processos
- Carregamento de dependências
- Conexão com banco de dados
- Execução manual do worker
- Análise de logs

**Uso:**
```bash
php diagnostico-worker.php
```

### 2. `teste-rapido.php`
Teste rápido para verificar se o worker está funcionando:
- Verifica se há tarefa pendente
- Limpa arquivo PID se necessário
- Executa worker manualmente
- Verifica resultado e logs

**Uso:**
```bash
php teste-rapido.php
```

### 3. `configurar-cron.php`
Configura automaticamente o cron job:
- Detecta sistema operacional
- Cria configuração apropriada
- Testa execução
- Fornece instruções finais

**Uso:**
```bash
php configurar-cron.php
```

### 4. `monitor-worker.php`
Monitor em tempo real do worker:
- Exibe status atual da análise
- Mostra progresso em tempo real
- Lista logs recentes
- Atualiza a cada 5 segundos

**Uso:**
```bash
php monitor-worker.php
```

## Worker Atualizado

O arquivo `worker.php` foi atualizado com:
- **Logging agressivo** em cada etapa
- **Tratamento de erros** melhorado
- **Mensagens detalhadas** para diagnóstico
- **Logs salvos** em `logs/worker.log`

## Passos para Resolução

### 1. Diagnóstico Inicial
```bash
cd bia-pine-app-to-php
php diagnostico-worker.php
```

### 2. Teste Manual
```bash
php teste-rapido.php
```

### 3. Configurar Execução Automática
```bash
php configurar-cron.php
```

### 4. Monitorar em Tempo Real
```bash
php monitor-worker.php
```

## Possíveis Causas do Problema

### 1. Worker não está sendo executado
- **Causa:** Cron job não configurado ou falhando
- **Solução:** Execute `php configurar-cron.php`

### 2. Erro fatal no worker
- **Causa:** Dependências não carregadas ou erro de sintaxe
- **Solução:** Verifique logs em `logs/worker.log`

### 3. Problema de permissões
- **Causa:** Diretórios não graváveis
- **Solução:** Verifique permissões de `cache/` e `logs/`

### 4. Problema de banco de dados
- **Causa:** Conexão falhando
- **Solução:** Verifique configuração em `config.php`

### 5. Processo travado
- **Causa:** Worker anterior não finalizou
- **Solução:** Execute `php clear-stuck-analysis.php`

## Estrutura do Sistema

O sistema usa um **sistema de arquivos baseado em lock** em vez de banco de dados:

- `cache/scan.lock` - Arquivo de controle da análise
- `cache/worker.pid` - Arquivo de controle do processo
- `logs/worker.log` - Logs detalhados do worker

## Estados da Análise

1. **pending** - Aguardando processamento
2. **running** - Em execução
3. **completed** - Concluída com sucesso
4. **failed** - Falhou com erro

## Comandos Úteis

### Limpar análise travada
```bash
php clear-stuck-analysis.php
```

### Verificar status via API
```bash
curl http://localhost/bia-pine-app-to-php/public/api/scan-status.php
```

### Iniciar nova análise
```bash
curl -X POST http://localhost/bia-pine-app-to-php/public/api/start-scan.php
```

### Ver logs em tempo real
```bash
tail -f logs/worker.log
```

## Configuração do Cron (Unix/Linux)

Adicione esta linha ao crontab:
```bash
* * * * * /caminho/completo/para/cron-worker.sh >> /caminho/completo/para/logs/cron.log 2>&1
```

Para editar o crontab:
```bash
crontab -e
```

## Configuração do Agendador de Tarefas (Windows)

1. Abra o Agendador de Tarefas
2. Crie uma nova tarefa
3. Configure para executar a cada minuto
4. Ação: Executar programa
5. Programa: `php`
6. Argumentos: `"C:\caminho\completo\para\worker.php"`
7. Diretório inicial: `"C:\caminho\completo\para\projeto"`

## Monitoramento

### Logs do Worker
- **Arquivo:** `logs/worker.log`
- **Formato:** `[timestamp] [level] message`
- **Níveis:** INFO, WARNING, ERROR

### Logs do Cron
- **Arquivo:** `logs/cron.log`
- **Conteúdo:** Saída do worker executado via cron

### Status via API
- **Endpoint:** `/public/api/scan-status.php`
- **Método:** GET
- **Resposta:** JSON com status atual

## Solução de Problemas

### Worker não executa
1. Execute `php diagnostico-worker.php`
2. Verifique se há erros nos logs
3. Teste execução manual: `php worker.php`

### Análise fica em "pending"
1. Verifique se o cron está configurado
2. Execute `php clear-stuck-analysis.php`
3. Inicie nova análise

### Erros de permissão
1. Verifique permissões dos diretórios
2. Execute: `chmod 755 cache logs`
3. Execute: `chmod 644 *.php`

### Problemas de banco
1. Verifique configuração em `config.php`
2. Teste conexão: `php check-and-fix-database.php`
3. Verifique se o banco existe

## Contato e Suporte

Para problemas adicionais:
1. Execute o diagnóstico completo
2. Verifique os logs detalhados
3. Teste cada componente individualmente
4. Documente os erros encontrados

---

**Nota:** Este sistema foi projetado para ser robusto e fornecer visibilidade completa sobre o processo de análise. Use os scripts de diagnóstico para identificar e resolver problemas rapidamente.
