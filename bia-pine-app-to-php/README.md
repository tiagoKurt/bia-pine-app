# BIA/PINE App - Versão PHP

Aplicação PHP para análise de dados do portal CKAN em busca de CPFs, convertida do Python original.

## 🏗️ Estrutura do Projeto (PSR-4)

```
bia-pine-app-to-php/
├── vendor/                   # Dependências do Composer
├── src/                      # Código fonte da aplicação (PSR-4)
│   ├── Api/                  # Controllers da API
│   │   └── StatusController.php
│   ├── Worker/               # Lógica de execução em lote
│   │   └── CkanScannerService.php
│   └── Cpf/                  # Centralização de funcionalidades de CPF
│       ├── CpfVerificationService.php
│       ├── Scanner/
│       │   ├── LogicBasedScanner.php
│       │   ├── AiBasedScanner.php
│       │   ├── CpfScannerInterface.php
│       │   └── Parser/
│       │       ├── FileParserFactory.php
│       │       ├── FileParserInterface.php
│       │       ├── CsvParser.php
│       │       ├── ExcelParser.php
│       │       ├── JsonParser.php
│       │       ├── PdfParser.php
│       │       └── TextParser.php
│       └── Ckan/             # Integração com CKAN
│           └── CkanApiClient.php
├── public/                   # Ponto de entrada do servidor web
│   ├── index.php             # Roteador da API
│   └── assets/               # Arquivos estáticos
├── bin/                      # Scripts executáveis
│   └── run_scanner.php       # Worker principal
├── cache/                    # Arquivos de cache, fila, lock/status
├── config.php                # Configurações de DB e ambiente
├── .env                      # Variáveis de ambiente
├── composer.json
└── README.md
```

## 🚀 Instalação

1. **Clone o repositório**
   ```bash
   git clone <repository-url>
   cd bia-pine-app-to-php
   ```

2. **Instale as dependências**
   ```bash
   composer install
   ```

3. **Configure o ambiente**
   ```bash
   cp .env.example .env
   # Edite o arquivo .env com suas configurações
   ```

4. **Configure o banco de dados**
   - Execute os scripts SQL em `config.php` para criar as tabelas necessárias

## 🔧 Configuração

### Variáveis de Ambiente (.env)

```env
# Banco de Dados
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=app_controladoria
DB_USERNAME=root
DB_PASSWORD=

# CKAN
CKAN_API_URL=https://dadosabertos.go.gov.br
CKAN_API_KEY=

# Memória e Performance
MEMORY_LIMIT=10G
MAX_EXECUTION_TIME=1800
PDF_MEMORY_LIMIT=4G
PDF_MAX_EXECUTION_TIME=1800
```

## 🏃‍♂️ Execução

### API Web
```bash
# Servidor de desenvolvimento
composer start
# ou
php -S localhost:8000 -t public
```

### Worker (Análise em Lote)
```bash
# Executar análise
php bin/run_scanner.php

# Forçar nova análise (ignora lock)
php bin/run_scanner.php --force
```

## 📡 API Endpoints

### GET /api/status
Retorna o status atual da análise.

**Resposta:**
```json
{
  "status": "running",
  "startTime": "2025-01-02T14:30:00+00:00",
  "progress": {
    "datasets_analisados": 150,
    "recursos_analisados": 1250,
    "recursos_com_cpfs": 45,
    "total_cpfs_salvos": 1200,
    "current_step": "Processando recurso 1251/5000: arquivo.csv"
  },
  "lastUpdate": "2025-01-02T14:45:00+00:00"
}
```

### POST /api/start
Inicia uma nova análise.

**Resposta:**
```json
{
  "status": "success",
  "message": "Análise iniciada com sucesso"
}
```

### POST /api/stop
Para a análise atual.

**Resposta:**
```json
{
  "status": "success",
  "message": "Análise parada com sucesso"
}
```

## 🧠 Otimizações de Memória

### Streaming de Arquivos
- **CSV/TXT**: Processamento linha por linha para reduzir uso de memória
- **PDF/DOCX**: Limpeza agressiva após processamento
- **Download**: Streaming direto para disco sem carregar na memória

### Limpeza Automática
- Coleta de lixo forçada a cada 10 recursos
- Limpeza de arquivos temporários após cada lote
- Verificação de limite de memória antes de continuar

### Configurações Recomendadas
```ini
memory_limit = 10G
max_execution_time = 1800
```

## 📊 Monitoramento

### Logs
- **Erros**: `logs/error.log`
- **Progresso**: Console do worker
- **Status**: `cache/scan_status.json`

### Métricas
- Uso de memória em tempo real
- Arquivos processados por lote
- CPFs encontrados por recurso
- Tempo de execução

## 🔄 Fluxo de Processamento

1. **Descoberta**: Varre todos os datasets do CKAN
2. **Fila**: Cria fila de recursos para processar
3. **Processamento**: Processa em lotes de 30 recursos
4. **Streaming**: Para CSV/TXT, processa em chunks de 64KB
5. **Limpeza**: Remove arquivos temporários e força GC
6. **Persistência**: Salva CPFs encontrados no banco

## 🛠️ Desenvolvimento

### Estrutura PSR-4
- Namespaces seguem a estrutura de pastas
- Autoload automático via Composer
- Separação clara entre API, Worker e CPF

### Adicionando Novos Parsers
1. Crie a classe em `src/Cpf/Scanner/Parser/`
2. Implemente `FileParserInterface`
3. Registre no `FileParserFactory`

### Adicionando Novos Scanners
1. Crie a classe em `src/Cpf/Scanner/`
2. Implemente `CpfScannerInterface`
3. Use no `CkanScannerService`

## 📝 Changelog

### v2.0.0
- ✅ Reorganização completa da estrutura (PSR-4)
- ✅ Centralização de funcionalidades de CPF
- ✅ Otimizações de memória com streaming
- ✅ API limpa e organizada
- ✅ Worker separado em pasta bin/
- ✅ Limpeza de arquivos desnecessários

### v1.0.0
- Versão inicial convertida do Python
