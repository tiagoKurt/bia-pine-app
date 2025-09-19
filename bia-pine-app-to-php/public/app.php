<?php
session_start();

require __DIR__ . '/../config.php';
require __DIR__ . '/../vendor/autoload.php';

use App\Bia;
use App\Pine;

$bia = new Bia();
try {
    $pine = new Pine();
} catch (Exception $e) {
    die('Erro fatal de conexão com o banco de dados: ' . $e->getMessage());
}


$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['messageType'] ?? 'error';
$downloadFile = $_SESSION['downloadFile'] ?? null;
$downloadFileName = $_SESSION['downloadFileName'] ?? null;
unset($_SESSION['message'], $_SESSION['messageType'], $_SESSION['downloadFile'], $_SESSION['downloadFileName']);

const DIAS_PARA_DESATUALIZADO = 30;
$portalUrl = $_SESSION['portalUrl'] ?? '';

$paginaAtual = isset($_GET['page']) && isset($_GET['tab']) && $_GET['tab'] === 'pine' ? (int)$_GET['page'] : 1;
$itensPorPagina = 15;

$analysisResults = []; 
if (!empty($portalUrl)) {
    $analysisResults = $pine->getDatasetsPaginados($portalUrl, $paginaAtual, $itensPorPagina);
}


$cpfFindings = [
    [
        'dataset_id' => 'dataset-exemplo-1',
        'dataset_name' => 'Dados de Cidadãos - 2024',
        'resource_id' => 'resource-123',
        'resource_name' => 'dados-cidadaos.csv',
        'resource_url' => 'https://dadosabertos.go.gov.br/dataset/dataset-exemplo-1/resource/resource-123',
        'resource_format' => 'csv',
        'cpf_count' => 15,
        'cpfs' => [
            '12345678901', '98765432100', '11122233344', '55566677788', '99988877766',
            '44433322211', '77788899900', '22233344455', '66677788899', '33344455566',
            '88899900011', '44455566677', '11122233344', '77788899900', '55566677788'
        ],
        'last_checked' => '2024-01-15 14:30:00'
    ],
    [
        'dataset_id' => 'dataset-exemplo-2',
        'dataset_name' => 'Cadastro de Funcionários',
        'resource_id' => 'resource-456',
        'resource_name' => 'funcionarios.xlsx',
        'resource_url' => 'https://dadosabertos.go.gov.br/dataset/dataset-exemplo-2/resource/resource-456',
        'resource_format' => 'xlsx',
        'cpf_count' => 8,
        'cpfs' => [
            '12345678901', '98765432100', '11122233344', '55566677788',
            '99988877766', '44433322211', '77788899900', '22233344455'
        ],
        'last_checked' => '2024-01-15 14:32:00'
    ]
];

$totalCpfs = array_sum(array_column($cpfFindings, 'cpf_count'));
$totalResources = count($cpfFindings);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Ação da aba BIA
    if ($action === 'gerar_dicionario') {
        $recursoUrl = $_POST['recurso_url'] ?? '';
        
        if (empty($recursoUrl)) {
            $_SESSION['message'] = 'Informe o link do recurso CKAN.';
            $_SESSION['messageType'] = 'error';
        } else {
            try {
                $templateFile = __DIR__ . '/../templates/modelo_bia2_pronto_para_preencher.docx';
                $outputFile = $bia->gerarDicionarioWord($recursoUrl, $templateFile);

                $_SESSION['message'] = 'Documento gerado e baixado com sucesso!';
                $_SESSION['messageType'] = 'success';
                $_SESSION['downloadFile'] = $outputFile;
                $_SESSION['downloadFileName'] = basename($outputFile);
                
            } catch (Exception $e) {
                $_SESSION['message'] = 'Ocorreu um erro ao gerar o dicionário: ' . $e->getMessage();
                $_SESSION['messageType'] = 'error';
            }
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=bia");
        exit;
    }
    
    if ($action === 'analyze_portal') {
        $portalUrl = $_POST['portal_url'] ?? '';
        $_SESSION['portalUrl'] = $portalUrl;

        if (empty($portalUrl) || !filter_var($portalUrl, FILTER_VALIDATE_URL)) {
            $_SESSION['message'] = 'Por favor, informe uma URL válida para o portal CKAN.';
            $_SESSION['messageType'] = 'error';
        } else {
            try {
                $pine->analisarESalvarPortal($portalUrl, DIAS_PARA_DESATUALIZADO); //
                $_SESSION['message'] = 'Análise concluída e dados salvos com sucesso!';
                $_SESSION['messageType'] = 'success';

            } catch (Exception $e) {
                $_SESSION['message'] = 'Ocorreu um erro ao analisar o portal: ' . $e->getMessage();
                $_SESSION['messageType'] = 'error';
            }
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=pine");
        exit;
    }
    
    if ($action === 'export_pine_csv') {
        $exportData = $pine->getDatasetsPaginados($portalUrl, 1, 99999); 
        
        if ($exportData && !empty($exportData['datasets'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="analise_pine_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8 no Excel
            fputcsv($output, ['ID', 'Nome do Dataset', 'Órgão', 'Última Atualização', 'Status', 'Dias Desde Atualização', 'Recursos', 'Link']);
            
            foreach ($exportData['datasets'] as $dataset) {
                fputcsv($output, [
                    $dataset['dataset_id'],
                    $dataset['name'],
                    $dataset['organization'],
                    $dataset['last_updated'] ? date('d/m/Y H:i', strtotime($dataset['last_updated'])) : 'N/A',
                    $dataset['status'],
                    $dataset['days_since_update'] === PHP_INT_MAX ? 'N/A' : $dataset['days_since_update'],
                    $dataset['resources_count'],
                    $dataset['url']
                ]);
            }
            
            fclose($output);
            exit;
        }
    }
    
    if ($action === 'export_cpf_csv') {
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
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoramento Portal de Dados Abertos - CGE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Seu CSS (sem alterações) */
        :root {
            --primary-color: #3d6b35;
            --primary-dark: #2d5a27;
            --secondary-color: #64748b;
            --success-color: #3d6b35;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --light-bg: #f8f9fa;
            --card-bg: #ffffff;
            --border-color: #dee2e6;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 4px 8px rgba(0, 0, 0, 0.15);
            --green-gradient: linear-gradient(135deg, #3d6b35 0%, #2d5a27 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .app-container {
            min-height: 100vh;
            background: #f5f5f5;
        }

        .header {
            background: var(--green-gradient);
            color: white;
            padding: 1.5rem 0;
            box-shadow: var(--shadow);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .system-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .system-logo i {
            font-size: 2rem;
            color: white;
        }

        .system-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .system-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }

        .government-logos {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cge-logo {
            text-align: center;
        }

        .cge-logo .logo-text {
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .gov-go-logo {
            text-align: center;
        }

        .gov-go-text {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffd700;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }

        .logo-image {
            max-height: 80px;
            width: auto;
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
            padding: 2rem 0;
        }

        .welcome-section {
            margin-bottom: 2rem;
        }

        .welcome-banner {
            background: var(--green-gradient);
            color: white;
            padding: 2rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .welcome-banner h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .welcome-banner p {
            font-size: 1rem;
            opacity: 0.9;
            margin: 0;
        }

        .module-access h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .nav-tabs {
            border: none;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            border: 2px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            border-radius: 8px;
            margin-right: 0.5rem;
            padding: 1rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-tabs .nav-link:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
            box-shadow: var(--shadow);
        }

        .nav-tabs .nav-link i {
            margin-right: 0.5rem;
        }

        .tab-content {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 2.5rem;
            border: 1px solid var(--border-color);
            min-height: 500px;
        }

        .tab-pane h2 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light-bg);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            font-weight: 500;
            box-shadow: var(--shadow);
        }

        .alert-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
            color: white;
        }

        .alert-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%);
            color: white;
        }

        .description-text {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .icon {
            font-size: 1.2em;
        }

        .download-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            text-align: center;
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
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-align: center;
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: var(--light-bg);
        }

        .badge {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
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

        .footer {
            background: var(--green-gradient);
            color: white;
            padding: 1.5rem 0;
            margin-top: 3rem;
        }

        .footer-content {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .footer-left {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .footer-right {
            display: flex;
            align-items: center;
        }

        .footer-info p {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .system-title {
                font-size: 1.2rem;
            }

            .system-subtitle {
                font-size: 0.8rem;
            }

            .tab-content {
                padding: 1.5rem;
            }
            
            .nav-tabs .nav-link {
                margin-right: 0.5rem;
                margin-bottom: 0.5rem;
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            .footer-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .welcome-banner h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="header">
            <div class="container">
                <div class="header-content">
                    <div class="header-left">
                        <div class="system-logo">
                            <i class="fas fa-chart-bar"></i>
                            <div>
                                <h1 class="system-title">Monitoramento Portal de Dados Abertos</h1>
                                <!-- <p class="system-subtitle">Sistema de Controle de Procedimentos Administrativos Correcionais</p> -->
                            </div>
                        </div>
                    </div>
                    <div class="header-right">
                        <div class="government-logos">
                            <div class="gov-go-logo">
                                <img src="assets/img/logo-cge-e-estado-goias.png" alt="CGE e Estado de Goiás" class="logo-image">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container">
                <?php if ($message && !isset($_GET['tab'])): ?>
                    <div class="alert alert-<?= $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-triangle') ?> icon"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Welcome Section -->
                <div class="welcome-section mb-4">
                    <div class="welcome-banner">
                        <h2>Bem-vindo ao Sistema de Monitoramento!</h2>
                        <p>Monitore métricas, verifique e analise o desempenho dos portais de dados abertos.</p>
                    </div>
                </div>

                <!-- Module Access -->
                <div class="module-access mb-4">
                    <h3>Você tem acesso aos seguintes módulos:</h3>
                </div>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs" id="mainTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'bia' ? 'active' : (!isset($_GET['tab']) ? 'active' : '') ?>" id="bia-tab" data-bs-toggle="tab" data-bs-target="#bia" type="button" role="tab">
                            <i class="fas fa-file-word icon"></i> BIA
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'pine' ? 'active' : '' ?>" id="pine-tab" data-bs-toggle="tab" data-bs-target="#pine" type="button" role="tab">
                            <i class="fas fa-chart-line icon"></i> PINE
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="cpf-tab" data-bs-toggle="tab" data-bs-target="#cpf" type="button" role="tab">
                            <i class="fas fa-shield-alt icon"></i> CPF
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="mainTabsContent">
                    <!-- BIA Tab -->
                    <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'bia' ? 'show active' : (!isset($_GET['tab']) ? 'show active' : '') ?>" id="bia" role="tabpanel">
                        <h2>
                            <i class="fas fa-file-word icon"></i>
                            Gerar Dicionário de Dados
                        </h2>
                        <p class="description-text">
                            Crie dicionários de dados em formato Word a partir de recursos CKAN. 
                            O sistema analisa automaticamente a estrutura dos dados e gera documentação completa.
                        </p>
                        
                        <form method="POST" class="mt-4" id="dicionario-form">
                            <input type="hidden" name="action" value="gerar_dicionario">
                            <div class="mb-4">
                                <label for="recurso_url" class="form-label">
                                    <i class="fas fa-link icon"></i> Link do Recurso CKAN
                                </label>
                                <input type="url" class="form-control" id="recurso_url" name="recurso_url" 
                                       placeholder="https://dadosabertos.go.gov.br/dataset/.../resource/..." required>
                                <div class="form-text text-muted mt-2">
                                    <i class="fas fa-info-circle icon"></i>
                                    Cole aqui o link completo do recurso que deseja documentar
                                </div>
                            </div>
                            <button type="submit" id="gerar-btn" class="btn btn-primary">
                                <span id="btn-text">
                                    <i class="fas fa-magic icon"></i> Gerar Dicionário
                                </span>
                                <span id="btn-loading" class="d-none">
                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    Gerando dicionário...
                                </span>
                            </button>
                        </form>
                        
                        <!-- Barra de progresso -->
                        <div id="progress-container" class="d-none mt-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-cogs icon"></i> Gerando Dicionário de Dados
                                    </h5>
                                    <p class="card-text text-muted">
                                        Analisando a estrutura dos dados e gerando documentação...
                                    </p>
                                    <div class="progress mb-3">
                                        <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                             role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted" id="progress-text">Iniciando...</small>
                                        <small class="text-muted" id="progress-percent">0%</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($downloadFile && file_exists($downloadFile)): ?>
                            <div id="download-notification" class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle icon"></i>
                                Documento gerado e baixado com sucesso!
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <script>
                                // Aguardar o Bootstrap carregar
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Download automático
                                    window.location.href = 'download.php?file=<?= urlencode($downloadFileName) ?>&path=<?= urlencode($downloadFile) ?>';
                                    
                                    // Remove o pop-up após 3 segundos
                                    setTimeout(function() {
                                        const notification = document.getElementById('download-notification');
                                        if (notification) {
                                            // Usar método nativo do Bootstrap 5
                                            if (typeof bootstrap !== 'undefined') {
                                                const bsAlert = bootstrap.Alert.getOrCreateInstance(notification);
                                                bsAlert.close();
                                            } else {
                                                // Fallback: remover manualmente
                                                notification.style.display = 'none';
                                            }
                                        }
                                    }, 3000);
                                });
                            </script>
                        <?php endif; ?>
                    </div>

                    <!-- PINE Tab -->
                    <div class="tab-pane fade" id="pine" role="tabpanel">
                        <h2>
                            <i class="fas fa-chart-line icon"></i>
                            Iniciar Análise PINE
                        </h2>
                        <p class="description-text">
                            Digite a URL do portal CKAN para analisar a atualização dos datasets. Os dados são salvos e exibidos abaixo.
                        </p>
                        
                        <form method="POST" id="analysis-form">
                            <input type="hidden" name="action" value="analyze_portal">
                            <div class="row">
                                <div class="col-md-8">
                                    <label for="portal_url" class="form-label">
                                        <i class="fas fa-link icon"></i> URL do Portal CKAN
                                    </label>
                                    <input type="url" class="form-control" id="portal_url" name="portal_url" 
                                           placeholder="https://dadosabertos.go.gov.br" 
                                           value="<?= htmlspecialchars($portalUrl) ?>" required>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" id="submit-btn" class="btn btn-primary w-100">
                                        <span id="btn-text"><i class="fas fa-play icon"></i> Iniciar Análise</span>
                                        <span id="loading-spinner" class="d-none">
                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            Analisando...
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <?php if ($analysisResults && !empty($analysisResults['datasets'])): ?>
                            <div class="mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h3>
                                        <i class="fas fa-list icon"></i>
                                        Lista de Datasets (<?= $analysisResults['total'] ?>)
                                    </h3>
                                    <div>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="export_pine_csv">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-download icon"></i> Exportar CSV
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Dataset</th>
                                                <th>Órgão</th>
                                                <th>Última Atualização</th>
                                                <th>Status</th>
                                                <th>Recursos</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analysisResults['datasets'] as $dataset): ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <strong><?= htmlspecialchars($dataset['name']) ?></strong><br>
                                                            <small class="text-muted">ID: <?= htmlspecialchars($dataset['dataset_id']) ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted"><?= htmlspecialchars($dataset['organization']) ?></span>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?= $dataset['last_updated'] ? date('d/m/Y', strtotime($dataset['last_updated'])) : 'N/A' ?></strong><br>
                                                            <small class="text-muted">
                                                                <?= $dataset['days_since_update'] !== PHP_INT_MAX ? $dataset['days_since_update'] . ' dias atrás' : 'Sem data' ?>
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($dataset['status'] === 'Atualizado'): ?>
                                                            <span class="badge bg-success"><i class="fas fa-check icon"></i> Atualizado</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger"><i class="fas fa-exclamation-triangle icon"></i> Desatualizado</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge bg-secondary"><?= $dataset['resources_count'] ?></span></td>
                                                    <td>
                                                        <a href="<?= htmlspecialchars($dataset['url']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-external-link-alt icon"></i> Ver
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if (isset($analysisResults['total_paginas']) && $analysisResults['total_paginas'] > 1): ?>
                                    <nav class="mt-4">
                                        <ul class="pagination justify-content-center" id="pagination">
                                            <li class="page-item <?= $paginaAtual <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="#" data-page="<?= $paginaAtual - 1 ?>">Anterior</a>
                                            </li>
                                            <?php for ($i = 1; $i <= $analysisResults['total_paginas']; $i++): ?>
                                                <li class="page-item <?= $i === $paginaAtual ? 'active' : '' ?>">
                                                    <a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?= $paginaAtual >= $analysisResults['total_paginas'] ? 'disabled' : '' ?>">
                                                <a class="page-link" href="#" data-page="<?= $paginaAtual + 1 ?>">Próximo</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="cpf" role="tabpanel">
                        <h2>
                            <i class="fas fa-shield-alt icon"></i>
                            Verificação de CPF
                        </h2>
                        <p class="description-text">
                            Auditoria de segurança em portais CKAN para detectar vazamentos de CPF em datasets públicos.
                        </p>

                        <?php if (empty($cpfFindings)): ?>
                            <!-- Nenhum CPF encontrado -->
                            <div class="stats-card success">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle icon me-3" style="font-size: 2rem;"></i>
                                    <div>
                                        <h4 class="mb-1">Nenhum CPF encontrado!</h4>
                                        <p class="mb-0">O portal está em conformidade com as práticas de proteção de dados.</p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Resumo dos resultados -->
                            <div class="stats-card">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <i class="fas fa-exclamation-triangle icon mb-2" style="font-size: 2rem;"></i>
                                            <h3><?= $totalResources ?></h3>
                                            <p class="mb-0">Recursos com CPFs</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <i class="fas fa-id-card icon mb-2" style="font-size: 2rem;"></i>
                                            <h3><?= $totalCpfs ?></h3>
                                            <p class="mb-0">Total de CPFs</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <i class="fas fa-clock icon mb-2" style="font-size: 2rem;"></i>
                                            <h3><?= date('H:i', strtotime($cpfFindings[0]['last_checked'])) ?></h3>
                                            <p class="mb-0">Última verificação</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabela de resultados -->
                            <div class="mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h3>
                                        <i class="fas fa-list icon"></i>
                                        Recursos com CPFs Detectados
                                    </h3>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="export_cpf_csv">
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
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <div class="container">
                <div class="footer-content">
                    <div class="footer-left">
                        <div class="government-logos">
                            <div class="gov-go-logo">
                                <img src="assets/img/logo-cge-e-estado-goias.png" alt="CGE e Estado de Goiás" class="logo-image">
                            </div>
                        </div>
                    </div>
                    <div class="footer-right">
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script para carregamento no botão de análise PINE
        document.getElementById('analysis-form').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const loadingSpinner = document.getElementById('loading-spinner');
            
            submitBtn.disabled = true;
            btnText.classList.add('d-none');
            loadingSpinner.classList.remove('d-none');
        });

        // Script para carregamento no botão de geração de dicionário BIA
        document.getElementById('dicionario-form').addEventListener('submit', function() {
            const gerarBtn = document.getElementById('gerar-btn');
            const btnText = document.getElementById('btn-text');
            const btnLoading = document.getElementById('btn-loading');
            const progressContainer = document.getElementById('progress-container');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const progressPercent = document.getElementById('progress-percent');
            
            // Desabilitar botão e mostrar loading
            gerarBtn.disabled = true;
            btnText.classList.add('d-none');
            btnLoading.classList.remove('d-none');
            
            // Mostrar barra de progresso
            progressContainer.classList.remove('d-none');
            
            // Simular progresso
            let progress = 0;
            const progressSteps = [
                { percent: 20, text: 'Conectando com a API CKAN...' },
                { percent: 40, text: 'Analisando estrutura dos dados...' },
                { percent: 60, text: 'Processando metadados...' },
                { percent: 80, text: 'Gerando documento Word...' },
                { percent: 100, text: 'Finalizando e preparando download...' }
            ];
            
            let currentStep = 0;
            const progressInterval = setInterval(() => {
                if (currentStep < progressSteps.length) {
                    const step = progressSteps[currentStep];
                    progressBar.style.width = step.percent + '%';
                    progressBar.setAttribute('aria-valuenow', step.percent);
                    progressText.textContent = step.text;
                    progressPercent.textContent = step.percent + '%';
                    currentStep++;
                } else {
                    clearInterval(progressInterval);
                }
            }, 800);
        });

        // Script para manter a aba ativa após o refresh da página
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const tabElement = document.querySelector('#' + tab + '-tab');
                if(tabElement) {
                    const tabInstance = new bootstrap.Tab(tabElement);
                    tabInstance.show();
                }
            }
            
            // Paginação AJAX
            const pagination = document.getElementById('pagination');
            if (pagination) {
                pagination.addEventListener('click', function(e) {
                    e.preventDefault();
                    const pageLink = e.target.closest('.page-link');
                    if (pageLink && !pageLink.parentElement.classList.contains('disabled')) {
                        const page = pageLink.getAttribute('data-page');
                        if (page) {
                            loadPinePage(page);
                        }
                    }
                });
            }
        });

        // Função para carregar página do PINE via AJAX
        function loadPinePage(page) {
            const portalUrl = document.getElementById('portal_url').value;
            if (!portalUrl) return;
            
            // Mostrar loading
            const tableContainer = document.querySelector('#pine .table-responsive');
            if (tableContainer) {
                tableContainer.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>';
            }
            
            // Fazer requisição AJAX
            fetch('pine.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=load_page&page=' + page + '&portal_url=' + encodeURIComponent(portalUrl)
            })
            .then(response => response.text())
            .then(data => {
                // Atualizar apenas a tabela e paginação
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const newTable = doc.querySelector('.table-responsive');
                const newPagination = doc.querySelector('#pagination');
                
                if (newTable && tableContainer) {
                    tableContainer.innerHTML = newTable.innerHTML;
                }
                
                if (newPagination) {
                    const paginationContainer = document.querySelector('#pagination').parentElement;
                    paginationContainer.innerHTML = newPagination.outerHTML;
                    
                    // Re-adicionar event listeners
                    const newPaginationEl = document.getElementById('pagination');
                    if (newPaginationEl) {
                        newPaginationEl.addEventListener('click', function(e) {
                            e.preventDefault();
                            const pageLink = e.target.closest('.page-link');
                            if (pageLink && !pageLink.parentElement.classList.contains('disabled')) {
                                const page = pageLink.getAttribute('data-page');
                                if (page) {
                                    loadPinePage(page);
                                }
                            }
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao carregar página:', error);
                if (tableContainer) {
                    tableContainer.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados da página.</div>';
                }
            });
        }
    </script>
</body>
</html>