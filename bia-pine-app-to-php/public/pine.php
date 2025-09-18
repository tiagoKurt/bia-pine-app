<?php
session_start();

require __DIR__ . '/../config.php';

require __DIR__ . '/../vendor/autoload.php';

use App\Pine;

$analysisResults = $_SESSION['analysisResults'] ?? null;
$portalUrl = $_SESSION['portalUrl'] ?? '';
$message = null;
$messageType = 'error';

const DIAS_PARA_DESATUALIZADO = 30;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'analyze_portal') {
        $portalUrl = $_POST['portal_url'] ?? '';
        $_SESSION['portalUrl'] = $portalUrl;
        
        if (empty($portalUrl) || !filter_var($portalUrl, FILTER_VALIDATE_URL)) {
            $message = 'Por favor, informe uma URL válida para o portal CKAN.';
            $analysisResults = null;
        } else {
            try {
                $pine = new Pine();
                $analysisResults = $pine->analisarPortal($portalUrl, DIAS_PARA_DESATUALIZADO);
                
                $_SESSION['analysisResults'] = $analysisResults;
                
                if (empty($analysisResults['datasets'])) {
                    $message = 'Nenhum dataset foi encontrado ou pôde ser processado no portal informado.';
                    $messageType = 'warning';
                }
                
            } catch (Exception $e) {
                $message = 'Ocorreu um erro ao analisar o portal: ' . $e->getMessage();
                $analysisResults = null;
            }
        }
    }
    
    if ($action === 'export_csv') {
        if ($analysisResults && !empty($analysisResults['datasets'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="analise_pine_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');

            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, ['ID', 'Nome do Dataset', 'Órgão', 'Última Atualização', 'Status', 'Dias Desde Atualização', 'Recursos', 'Link']);
            
            foreach ($analysisResults['datasets'] as $dataset) {
                fputcsv($output, [
                    $dataset['id'],
                    $dataset['name'],
                    $dataset['organization'],
                    $dataset['last_updated'] ? date('d/m/Y H:i', strtotime($dataset['last_updated'])) : 'N/A',
                    $dataset['status'] === 'updated' ? 'Atualizado' : 'Desatualizado',
                    $dataset['days_since_update'] === PHP_INT_MAX ? 'N/A' : $dataset['days_since_update'],
                    $dataset['resources_count'],
                    $dataset['url']
                ]);
            }
            
            fclose($output);
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise PINE - BIA-PINE App</title>
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

        .form-section {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
        }

        .form-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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

        .feature-card {
            background: var(--light-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
        }

        .feature-card h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .feature-card p {
            color: var(--text-secondary);
            margin: 0;
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
        <header class="header">
            <div class="container">
                <h1><i class="fas fa-database icon"></i> BIA-PINE App</h1>
                <p>Ferramentas inteligentes para gestão de dados CKAN</p>
            </div>
        </header>

        <div class="main-content">
            <div class="container">
                <div class="row">
                    <div class="col-lg-3">
                        </div>

                    <div class="col-lg-9">
                        <div class="content-card">
                            <h2>
                                <i class="fas fa-search icon"></i>
                                Iniciar Análise PINE
                            </h2>
                            <p class="text-muted mb-4">
                                Digite a URL do portal CKAN para analisar a atualização dos datasets. O processo pode levar alguns minutos.
                            </p>
                            
                            <?php if ($message): ?>
                                <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'warning' ?> alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($message) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            
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
                        </div>

                        <?php if ($analysisResults && !empty($analysisResults['datasets'])): ?>
                            <div class="content-card">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h2>
                                        <i class="fas fa-list icon"></i>
                                        Lista de Datasets (<?= $analysisResults['total_datasets'] ?>)
                                    </h2>
                                    <div>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="export_csv">
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
                                                            <strong><?= htmlspecialchars($dataset['name']) ?></strong>
                                                            <br>
                                                            <small class="text-muted">ID: <?= htmlspecialchars($dataset['id']) ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted"><?= htmlspecialchars($dataset['organization']) ?></span>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?= $dataset['last_updated'] ? date('d/m/Y', strtotime($dataset['last_updated'])) : 'N/A' ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?= $dataset['days_since_update'] !== PHP_INT_MAX ? $dataset['days_since_update'] . ' dias atrás' : 'Sem data' ?>
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($dataset['status'] === 'updated'): ?>
                                                            <span class="badge bg-success"><i class="fas fa-check icon"></i> Atualizado</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger"><i class="fas fa-exclamation-triangle icon"></i> Desatualizado</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?= $dataset['resources_count'] ?></span>
                                                    </td>
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
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script para mostrar um feedback de carregamento no botão
        document.getElementById('analysis-form').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const loadingSpinner = document.getElementById('loading-spinner');

            submitBtn.disabled = true;
            btnText.classList.add('d-none');
            loadingSpinner.classList.remove('d-none');
        });
    </script>
</body>
</html>