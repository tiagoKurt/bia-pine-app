<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Bia;

$bia = new Bia();

$message = '';
$messageType = '';
$downloadFile = null;
$downloadFileName = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'gerar_dicionario') {
        $recursoUrl = $_POST['recurso_url'] ?? '';
        
        if (empty($recursoUrl)) {
            $message = 'Informe o link do recurso CKAN.';
            $messageType = 'error';
        } else {
            try {
                $templateFile = __DIR__ . '/../templates/modelo_bia2_pronto_para_preencher.docx';

                $outputFile = $bia->gerarDicionarioWord($recursoUrl, $templateFile);

                $message = 'Documento gerado com sucesso.';
                $messageType = 'success';
                $downloadFile = $outputFile;
                $downloadFileName = basename($outputFile);
                
            } catch (Exception $e) {
                $message = 'Ocorreu um erro ao gerar o dicionário: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIA-PINE App - Ferramentas CKAN</title>
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
                <h1><i class="fas fa-database icon"></i> BIA-PINE App</h1>
                <p>Ferramentas inteligentes para gestão de dados CKAN</p>
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
                                <button class="nav-button active" onclick="showSection('gerar-dicionario')">
                                    <i class="fas fa-file-word icon"></i>
                                    Gerar Dicionário
                                </button>

                            </div>
                            
                            <!-- Feature Cards -->
                            <div class="feature-card">
                                <h4><i class="fas fa-lightbulb icon"></i> Dica</h4>
                                <p>Use URLs válidas de portais CKAN para obter os melhores resultados</p>
                            </div>
                        </div>
                    </div>

                    <!-- Content Area -->
                    <div class="col-lg-9">
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> icon"></i>
                                <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Seção: Gerar Dicionário -->
                        <div id="gerar-dicionario" class="form-section active">
                            <div class="content-card">
                                <h2>
                                    <i class="fas fa-file-word icon"></i>
                                    Gerar Dicionário de Dados
                                </h2>
                                <p class="description-text">
                                    Crie dicionários de dados em formato Word a partir de recursos CKAN. 
                                    O sistema analisa automaticamente a estrutura dos dados e gera documentação completa.
                                </p>
                                
                                <form method="POST" class="mt-4">
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
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-magic icon"></i> Gerar Dicionário
                                    </button>
                                </form>
                                
                                <?php if ($downloadFile && file_exists($downloadFile)): ?>
                                    <div class="download-section">
                                        <h4 class="text-primary mb-3">
                                            <i class="fas fa-download icon"></i> Documento Gerado com Sucesso!
                                        </h4>
                                        <a href="download.php?file=<?= urlencode($downloadFileName) ?>&path=<?= urlencode($downloadFile) ?>" class="btn btn-success">
                                            <i class="fas fa-download icon"></i> Baixar Documento Word
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>


                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.nav-button').forEach(button => {
                button.classList.remove('active');
            });
            
            event.target.closest('.nav-button').classList.add('active');
            
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            document.getElementById(sectionId).classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>