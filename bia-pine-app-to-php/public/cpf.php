<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Bia;

$bia = new Bia();

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
            '12345678901',
            '98765432100',
            '11122233344',
            '55566677788',
            '99988877766',
            '44433322211',
            '77788899900',
            '22233344455',
            '66677788899',
            '33344455566',
            '88899900011',
            '44455566677',
            '11122233344',
            '77788899900',
            '55566677788'
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
            '12345678901',
            '98765432100',
            '11122233344',
            '55566677788',
            '99988877766',
            '44433322211',
            '77788899900',
            '22233344455'
        ],
        'last_checked' => '2024-01-15 14:32:00'
    ],
    [
        'dataset_id' => 'dataset-exemplo-3',
        'dataset_name' => 'Relatório de Beneficiários',
        'resource_id' => 'resource-789',
        'resource_name' => 'beneficiarios.pdf',
        'resource_url' => 'https://dadosabertos.go.gov.br/dataset/dataset-exemplo-3/resource/resource-789',
        'resource_format' => 'pdf',
        'cpf_count' => 3,
        'cpfs' => [
            '12345678901',
            '98765432100',
            '11122233344'
        ],
        'last_checked' => '2024-01-15 14:35:00'
    ]
];

$totalCpfs = array_sum(array_column($cpfFindings, 'cpf_count'));
$totalResources = count($cpfFindings);

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
    <title>Verificação de CPF - BIA-PINE App</title>
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
                <h1><i class="fas fa-shield-alt icon"></i> Verificação de CPF</h1>
                <p>Auditoria de segurança em portais CKAN</p>
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
                            <div class="content-card">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h2>
                                        <i class="fas fa-list icon"></i>
                                        Recursos com CPFs Detectados
                                    </h2>
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
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
