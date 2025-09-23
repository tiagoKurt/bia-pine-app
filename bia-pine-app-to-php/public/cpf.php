<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Bia;

// Incluir funções de verificação de CPF
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/functions.php';

$bia = new Bia();

// Variáveis para controle de estado
$mensagem = '';
$tipoMensagem = 'info';
$cpfFindings = [];
$totalCpfs = 0;
$totalResources = 0;
$verificacoes = [];
$estatisticas = [];

// Verificar se a conexão com o banco está funcionando
$dbConnected = false;
if ($pdo) {
    $dbConnected = true;
    
    // Lógica de processamento do formulário de verificação individual
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cpf_individual'])) {
        $cpfInput = $_POST['cpf_individual'];
        $cpfLimpo = limparCPF($cpfInput);
        $observacoes = trim($_POST['observacoes'] ?? '');
        
        if (strlen($cpfLimpo) === 11) {
            $isValid = validaCPF($cpfLimpo);
            
            if (salvarVerificacaoCPF($pdo, $cpfLimpo, $isValid, $observacoes ?: null, 'manual')) {
                $status = $isValid ? "VÁLIDO" : "INVÁLIDO";
                $mensagem = "CPF " . formatarCPF($cpfInput) . " verificado como {$status} e salvo com sucesso!";
                $tipoMensagem = 'success';
            } else {
                $mensagem = "Erro ao salvar a verificação do CPF.";
                $tipoMensagem = 'error';
            }
        } else {
            $mensagem = "CPF inválido. Digite um CPF com 11 dígitos.";
            $tipoMensagem = 'error';
        }
    }
    
    // Buscar dados reais do banco de dados
    $verificacoes = buscarTodasVerificacoes($pdo, 50);
    $estatisticas = obterEstatisticasVerificacoes($pdo);
    
    // Buscar verificações por fonte CKAN
    $verificacoesCkan = buscarVerificacoesPorFonte($pdo, 'ckan_scanner', 20);
    
    // Converter para formato compatível com a interface existente
    $cpfFindings = [];
    if (!empty($verificacoesCkan)) {
        // Agrupar por identificador_fonte para formar recursos
        $recursos = [];
        foreach ($verificacoesCkan as $verificacao) {
            $identificador = $verificacao['identificador_fonte'] ?? 'unknown-' . $verificacao['id'];
            if (!isset($recursos[$identificador])) {
                $recursos[$identificador] = [
                    'dataset_id' => 'ckan-dataset-' . $verificacao['id'],
                    'dataset_name' => 'Dataset CKAN - ' . date('d/m/Y', strtotime($verificacao['data_verificacao'])),
                    'resource_id' => 'resource-' . $verificacao['id'],
                    'resource_name' => 'recurso-verificado.txt',
                    'resource_url' => '#',
                    'resource_format' => 'txt',
                    'cpf_count' => 0,
                    'cpfs' => [],
                    'last_checked' => $verificacao['data_verificacao']
                ];
            }
            $recursos[$identificador]['cpfs'][] = $verificacao['cpf'];
            $recursos[$identificador]['cpf_count']++;
        }
        
        $cpfFindings = array_values($recursos);
    }
    
    $totalCpfs = $estatisticas['total'] ?? 0;
    $totalResources = count($cpfFindings);
} else {
    // Se não houver conexão com banco, mostrar mensagem de erro
    $cpfFindings = [];
    $verificacoes = [];
    $estatisticas = ['total' => 0, 'validos' => 0, 'invalidos' => 0];
    $totalCpfs = 0;
    $totalResources = 0;
    $mensagem = "Erro de conexão com o banco de dados. Verifique as configurações.";
    $tipoMensagem = 'error';
}

if (isset($_POST['action']) && $_POST['action'] === 'export_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="cpf_findings_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Dataset ID', 'Dataset Name', 'Resource Name', 'Resource Format', 'CPF Count', 'Last Checked']);
    
    foreach ($cpfFindings as $finding) {
        fputcsv($output, [
            $finding['dataset_id'],
            $finding['dataset_name'],
            $finding['resource_name'],
            $finding['resource_format'],
            $finding['cpf_count'],
            $finding['last_checked']
        ]);
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de CPF - Monitoramento Portal de Dados Abertos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .app-container {
            min-height: 100vh;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem 0;
            text-align: center;
            box-shadow: var(--shadow-lg);
        }

        .header h1 {
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .main-content {
            padding: 3rem 0;
        }

        .sidebar {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .sidebar h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            text-align: center;
        }

        .nav-button {
            width: 100%;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            border: 2px solid var(--primary-color);
            background: transparent;
            color: var(--primary-color);
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .nav-button:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .nav-button.active {
            background: var(--primary-color);
            color: white;
            box-shadow: var(--shadow);
        }

        .content-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 2.5rem;
            border: 1px solid var(--border-color);
            min-height: 500px;
        }

        .content-card h2 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .stats-card.success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
        }

        .table {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--border-color);
        }

        .badge {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }

        .btn {
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .icon {
            font-size: 1.2em;
        }

        .accordion-button:not(.collapsed) {
            background-color: var(--light-bg);
            color: var(--primary-color);
        }

        .accordion-button:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .cpf-list {
            font-family: 'Courier New', monospace;
            background: var(--light-bg);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .cpf-item {
            display: inline-block;
            background: var(--danger-color);
            color: white;
            padding: 0.25rem 0.5rem;
            margin: 0.25rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .mensagem {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .mensagem.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .mensagem.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .mensagem.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .verification-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .verification-table th,
        .verification-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .verification-table th {
            background-color: var(--light-bg);
            font-weight: 600;
            color: var(--text-primary);
        }

        .verification-table tr:hover {
            background-color: var(--light-bg);
        }

        .valido {
            color: var(--success-color);
            font-weight: bold;
        }

        .invalido {
            color: var(--danger-color);
            font-weight: bold;
        }

        .cpf-formatted {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }

        .date-formatted {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .no-data {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
            font-style: italic;
        }

        .tab-content {
            margin-top: 2rem;
        }

        .nav-tabs .nav-link {
            border-radius: 8px 8px 0 0;
            border: 1px solid var(--border-color);
            background: var(--light-bg);
            color: var(--text-secondary);
            font-weight: 500;
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .nav-tabs .nav-link:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .content-card {
                padding: 1.5rem;
            }
            
            .sidebar {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="header">
            <div class="container">
                <h1><i class="fas fa-chart-bar icon"></i> Monitoramento Portal de Dados Abertos</h1>
                <!-- <p>Sistema de Controle de Procedimentos Administrativos Correcionais</p> -->
            </div>
        </header>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container">
                <div class="row">
                    <!-- Sidebar -->
                    <div class="col-lg-3">
                        <div class="sidebar">
                            <h3><i class="fas fa-tools icon"></i> Ferramentas</h3>
                            <div class="nav flex-column">
                                <a href="index.php" class="nav-button">
                                    <i class="fas fa-file-word icon"></i>
                                    Gerar Dicionário
                                </a>
                                <a href="cpf.php" class="nav-button active">
                                    <i class="fas fa-shield-alt icon"></i>
                                    Verificação de CPF
                                </a>
                                <a href="pine.php" class="nav-button">
                                    <i class="fas fa-chart-line icon"></i>
                                    Análise PINE
                                </a>
                            </div>
                            
                            <!-- Feature Cards -->
                            <div class="feature-card">
                                <h4><i class="fas fa-lightbulb icon"></i> Dica</h4>
                                <p>Verificação automática de vazamentos de CPF em todos os recursos do portal</p>
                            </div>
                        </div>
                    </div>

                    <!-- Content Area -->
                    <div class="col-lg-9">
                        <?php if ($mensagem): ?>
                            <div class="mensagem <?php echo $tipoMensagem; ?>">
                                <?php echo htmlspecialchars($mensagem); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Resumo dos resultados -->
                        <div class="stats-card">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <i class="fas fa-exclamation-triangle icon mb-2" style="font-size: 2rem;"></i>
                                        <h3><?= $totalResources ?></h3>
                                        <p class="mb-0">Recursos com CPFs</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <i class="fas fa-id-card icon mb-2" style="font-size: 2rem;"></i>
                                        <h3><?= $totalCpfs ?></h3>
                                        <p class="mb-0">Total de CPFs</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <i class="fas fa-check-circle icon mb-2" style="font-size: 2rem;"></i>
                                        <h3><?= $estatisticas['validos'] ?? 0 ?></h3>
                                        <p class="mb-0">CPFs Válidos</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <i class="fas fa-clock icon mb-2" style="font-size: 2rem;"></i>
                                        <h3><?= !empty($cpfFindings) ? date('H:i', strtotime($cpfFindings[0]['last_checked'])) : '--:--' ?></h3>
                                        <p class="mb-0">Última verificação</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Botão de Análise CKAN -->
                            <div class="row mt-4">
                                <div class="col-12 text-center">
                                    <button id="btnScanCkan" class="btn btn-warning btn-lg" <?= !$dbConnected ? 'disabled' : '' ?>>
                                        <i class="fas fa-search icon"></i>
                                        Executar Análise CKAN
                                    </button>
                                    <p class="mt-2 mb-0 text-white-50">
                                        <small>Analisa todos os recursos do portal de dados abertos em busca de vazamentos de CPF</small>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Abas de Navegação -->
                        <div class="content-card">
                            <ul class="nav nav-tabs" id="cpfTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="verification-tab" data-bs-toggle="tab" data-bs-target="#verification" type="button" role="tab">
                                        <i class="fas fa-shield-alt icon"></i> Verificação Individual
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                                        <i class="fas fa-history icon"></i> Histórico
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="findings-tab" data-bs-toggle="tab" data-bs-target="#findings" type="button" role="tab">
                                        <i class="fas fa-search icon"></i> Descobertas CKAN
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="cpfTabsContent">
                                <!-- Aba de Verificação Individual -->
                                <div class="tab-pane fade show active" id="verification" role="tabpanel">
                                    <div class="mt-4">
                                        <h3><i class="fas fa-user-check icon"></i> Verificar CPF Individual</h3>
                                        <p class="text-muted">Digite um CPF para verificar sua validade e salvar no banco de dados.</p>
                                        
                                        <form method="POST" class="mt-4">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="cpf_individual">CPF:</label>
                                                        <input 
                                                            type="text" 
                                                            id="cpf_individual" 
                                                            name="cpf_individual" 
                                                            placeholder="000.000.000-00" 
                                                            maxlength="14"
                                                            required
                                                            pattern="[0-9.-]{11,14}"
                                                            title="Digite um CPF válido (formato: 000.000.000-00)"
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="observacoes">Observações (opcional):</label>
                                                        <input 
                                                            type="text" 
                                                            id="observacoes" 
                                                            name="observacoes" 
                                                            placeholder="Adicione observações sobre esta verificação..."
                                                            maxlength="500"
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-check icon"></i> Verificar e Salvar
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Aba de Histórico -->
                                <div class="tab-pane fade" id="history" role="tabpanel">
                                    <div class="mt-4">
                                        <h3><i class="fas fa-list icon"></i> Histórico de Verificações</h3>
                                        <p class="text-muted">Últimas verificações realizadas no sistema.</p>
                                        
                                        <?php if (!empty($verificacoes)): ?>
                                            <table class="verification-table">
                                                <thead>
                                                    <tr>
                                                        <th>CPF</th>
                                                        <th>Status</th>
                                                        <th>Data da Verificação</th>
                                                        <th>Observações</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($verificacoes as $row): ?>
                                                        <tr>
                                                            <td class="cpf-formatted">
                                                                <?php echo htmlspecialchars(formatarCPF($row['cpf'])); ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($row['e_valido']): ?>
                                                                    <span class="valido">✓ Válido</span>
                                                                <?php else: ?>
                                                                    <span class="invalido">✗ Inválido</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="date-formatted">
                                                                <?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($row['data_verificacao']))); ?>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($row['observacoes'] ?? ''); ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <div class="no-data">
                                                Nenhuma verificação encontrada. Verifique um CPF para começar!
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Aba de Descobertas CKAN -->
                                <div class="tab-pane fade" id="findings" role="tabpanel">
                                    <div class="mt-4">
                                        <h3><i class="fas fa-search icon"></i> Descobertas do Scanner CKAN</h3>
                                        <p class="text-muted">CPFs detectados automaticamente em portais de dados abertos.</p>
                                        
                                        <?php if (empty($cpfFindings)): ?>
                                            <!-- Nenhum CPF encontrado ou análise não executada -->
                                            <div class="stats-card success">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-info-circle icon me-3" style="font-size: 2rem;"></i>
                                                    <div>
                                                        <h4 class="mb-1">Nenhuma análise executada</h4>
                                                        <p class="mb-0">Execute a análise CKAN para verificar vazamentos de CPF nos recursos do portal de dados abertos.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="text-center mt-4">
                                                <button id="btnScanCkanTab" class="btn btn-warning btn-lg" <?= !$dbConnected ? 'disabled' : '' ?>>
                                                    <i class="fas fa-search icon"></i>
                                                    Executar Análise CKAN
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <!-- Tabela de resultados -->
                                            <div class="d-flex justify-content-between align-items-center mb-4">
                                                <h4>
                                                    <i class="fas fa-list icon"></i>
                                                    Recursos com CPFs Detectados
                                                </h4>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="export_csv">
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-download icon"></i> Exportar CSV
                                                    </button>
                                                </form>
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Dataset</th>
                                                            <th>Recurso</th>
                                                            <th>Formato</th>
                                                            <th>CPFs</th>
                                                            <th>Verificado</th>
                                                            <th>Ações</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($cpfFindings as $index => $finding): ?>
                                                            <tr>
                                                                <td>
                                                                    <div>
                                                                        <strong><?= htmlspecialchars($finding['dataset_name']) ?></strong>
                                                                        <br>
                                                                        <small class="text-muted"><?= htmlspecialchars($finding['dataset_id']) ?></small>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div>
                                                                        <strong><?= htmlspecialchars($finding['resource_name']) ?></strong>
                                                                        <br>
                                                                        <a href="<?= htmlspecialchars($finding['resource_url']) ?>" target="_blank" class="text-decoration-none">
                                                                            <small><i class="fas fa-external-link-alt"></i> Ver recurso</small>
                                                                        </a>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-secondary"><?= strtoupper($finding['resource_format']) ?></span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-danger"><?= $finding['cpf_count'] ?> CPFs</span>
                                                                </td>
                                                                <td>
                                                                    <small><?= date('d/m/Y H:i', strtotime($finding['last_checked'])) ?></small>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>" aria-expanded="false">
                                                                        <i class="fas fa-eye icon"></i> Ver CPFs
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="6" class="p-0">
                                                                    <div class="collapse" id="collapse<?= $index ?>">
                                                                        <div class="p-3 bg-light">
                                                                            <h6 class="mb-3">
                                                                                <i class="fas fa-id-card icon"></i>
                                                                                CPFs Encontrados (<?= $finding['cpf_count'] ?>)
                                                                            </h6>
                                                                            <div class="cpf-list">
                                                                                <?php foreach ($finding['cpfs'] as $cpf): ?>
                                                                                    <span class="cpf-item"><?= $cpf ?></span>
                                                                                <?php endforeach; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Máscara para formatação do CPF
        document.getElementById('cpf_individual').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                e.target.value = value;
            }
        });

        // Validação em tempo real
        document.getElementById('cpf_individual').addEventListener('blur', function(e) {
            const cpf = e.target.value.replace(/\D/g, '');
            if (cpf.length === 11) {
                console.log('CPF com 11 dígitos:', cpf);
            }
        });

        // Scanner CKAN - Botão principal
        document.getElementById('btnScanCkan').addEventListener('click', function() {
            executeScanCkan();
        });

        // Scanner CKAN - Botão da aba
        document.getElementById('btnScanCkanTab').addEventListener('click', function() {
            executeScanCkan();
        });

        // Função para executar o scanner CKAN
        function executeScanCkan() {
            const btn1 = document.getElementById('btnScanCkan');
            const btn2 = document.getElementById('btnScanCkanTab');
            const originalText1 = btn1.innerHTML;
            const originalText2 = btn2.innerHTML;
            
            // Desabilitar botões e mostrar loading
            btn1.disabled = true;
            btn2.disabled = true;
            btn1.innerHTML = '<i class="fas fa-spinner fa-spin icon"></i> Analisando...';
            btn2.innerHTML = '<i class="fas fa-spinner fa-spin icon"></i> Analisando...';
            
            // Mostrar modal de progresso
            showProgressModal();
            
            // Executar scanner via AJAX
            fetch('api/scan-ckan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Sucesso - recarregar página para mostrar resultados
                    showMessage('Análise CKAN concluída com sucesso! ' + data.data.recursos_encontrados + ' recursos analisados.', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    // Erro
                    showMessage('Erro na análise CKAN: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showMessage('Erro de conexão durante a análise CKAN', 'error');
            })
            .finally(() => {
                // Reabilitar botões
                btn1.disabled = false;
                btn2.disabled = false;
                btn1.innerHTML = originalText1;
                btn2.innerHTML = originalText2;
                hideProgressModal();
            });
        }

        // Função para mostrar mensagem
        function showMessage(message, type) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const messageDiv = document.createElement('div');
            messageDiv.className = `alert ${alertClass} alert-dismissible fade show`;
            messageDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Inserir no topo da página
            const container = document.querySelector('.main-content .container');
            container.insertBefore(messageDiv, container.firstChild);
            
            // Auto-remover após 5 segundos
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 5000);
        }

        // Modal de progresso
        function showProgressModal() {
            const modalHtml = `
                <div class="modal fade" id="progressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-search icon"></i> Análise CKAN em Andamento
                                </h5>
                            </div>
                            <div class="modal-body text-center">
                                <div class="spinner-border text-primary mb-3" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                                <p>Analisando recursos do portal de dados abertos...</p>
                                <p class="text-muted"><small>Este processo pode levar alguns minutos dependendo do tamanho do portal.</small></p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modal existente se houver
            const existingModal = document.getElementById('progressModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Adicionar modal ao body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('progressModal'));
            modal.show();
        }

        function hideProgressModal() {
            const modal = document.getElementById('progressModal');
            if (modal) {
                const bootstrapModal = bootstrap.Modal.getInstance(modal);
                if (bootstrapModal) {
                    bootstrapModal.hide();
                }
                modal.remove();
            }
        }

        // Inicializar tooltips do Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>
