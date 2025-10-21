<?php
/**
 * Conteúdo da aba CPF - Verificação de CPF em Datasets
 */
?>

<h2>
    <i class="fas fa-shield-alt icon"></i>
    Verificação de CPF
</h2>
<p class="description-text">
    Auditoria de segurança em portais CKAN para detectar vazamentos de CPF em datasets públicos.
</p>

<!-- Status da Análise -->
<div class="cpf-status-section mb-4">
    <?php if (empty($cpfFindings) && ($cpfData['total_resources'] ?? 0) == 0): ?>
        <div class="card border-info">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="status-icon bg-info">
                            <i class="fas fa-info-circle"></i>
                        </div>
                    </div>
                    <div class="col">
                        <h5 class="card-title mb-1 text-info">Pronto para Análise</h5>
                        <p class="card-text mb-0">Execute a análise CKAN para verificar vazamentos de CPF nos recursos do portal de dados abertos.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif (!empty($cpfFindings) || ($cpfData['total_resources'] ?? 0) > 0): ?>
        <div class="card border-warning">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="status-icon bg-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="col">
                        <h5 class="card-title mb-1 text-warning">CPFs Detectados</h5>
                        <p class="card-text mb-2">
                            <?php if ($lastScanInfo): ?>
                                Última análise: <?= date('d/m/Y H:i', strtotime($lastScanInfo['lastScan'])) ?>
                            <?php else: ?>
                                Dados históricos disponíveis no banco de dados
                            <?php endif; ?>
                        </p>
                        <small class="text-muted">
                            <i class="fas fa-database"></i> 
                            <?= number_format($cpfData['total_resources'] ?? 0, 0, ',', '.') ?> recursos com CPFs encontrados
                        </small>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Controles de Análise -->
<div class="cpf-controls-section mb-4">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-cogs"></i> Controles de Análise
            </h5>
        </div>
        <div class="card-body">
            <div class="row align-items-center mb-3">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="status-indicator bg-primary me-3"></div>
                        <div>
                            <h6 class="mb-1">Análise CKAN</h6>
                            <p class="mb-0 text-muted">Execute a análise para detectar CPFs nos datasets.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button id="btnScanCkan" class="btn btn-primary btn-lg">
                        <i class="fas fa-search me-2"></i>
                        Executar Análise
                    </button>
                </div>
            </div>
            
            <hr class="my-3">
            
            <!-- Nova Ferramenta: Anonimização de Arquivos -->
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="status-indicator bg-success me-3"></div>
                        <div>
                            <h6 class="mb-1">Anonimizar CPFs em Arquivos</h6>
                            <p class="mb-0 text-muted">Faça upload de arquivos (CSV, Excel, PDF) para detectar e anonimizar CPFs automaticamente.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#modalAnonymizer">
                        <i class="fas fa-file-upload me-2"></i>
                        Anonimizar Arquivo
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Anonimizador -->
<div class="modal fade" id="modalAnonymizer" tabindex="-1" aria-labelledby="modalAnonymizerLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAnonymizerLabel">
                    <i class="fas fa-user-secret me-2"></i>
                    Anonimizar CPFs em Arquivos
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <!-- Upload Area -->
                <div id="uploadArea" class="upload-area text-center p-5 border border-2 border-dashed rounded">
                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                    <h5>Arraste um arquivo ou clique para selecionar</h5>
                    <p class="text-muted mb-3">Formatos suportados: CSV, Excel (.xlsx, .xls)</p>
                    <p class="text-muted small">Tamanho máximo: 10MB</p>
                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Nota:</strong> PDFs não são suportados. Converta para Excel antes de processar.
                    </div>
                    <input type="file" id="fileInput" class="d-none" accept=".csv,.xlsx,.xls">
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-folder-open me-2"></i>
                        Selecionar Arquivo
                    </button>
                </div>
                
                <!-- Progress Area -->
                <div id="progressArea" class="d-none mt-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-file me-2"></i>
                        <span id="fileName" class="fw-bold"></span>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                    </div>
                    <p id="progressText" class="text-muted mt-2 mb-0"></p>
                </div>
                
                <!-- Results Area -->
                <div id="resultsArea" class="d-none mt-4">
                    <div class="alert alert-success">
                        <h6 class="alert-heading">
                            <i class="fas fa-check-circle me-2"></i>
                            Processamento Concluído!
                        </h6>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Arquivo Original:</strong></p>
                                <p id="resultOriginalFile" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>CPFs Encontrados:</strong></p>
                                <p id="resultCpfCount" class="text-muted"></p>
                            </div>
                        </div>
                        
                        <div id="cpfListContainer" class="mt-3 d-none">
                            <p class="mb-2"><strong>CPFs Detectados:</strong></p>
                            <div id="cpfList" class="p-3 bg-light rounded" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        
                        <div class="mt-3">
                            <button id="btnDownload" class="btn btn-success">
                                <i class="fas fa-download me-2"></i>
                                Baixar Arquivo Anonimizado
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetAnonymizer()">
                                <i class="fas fa-redo me-2"></i>
                                Processar Outro Arquivo
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Error Area -->
                <div id="errorArea" class="d-none mt-4">
                    <div class="alert alert-danger">
                        <h6 class="alert-heading">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Erro no Processamento
                        </h6>
                        <p id="errorMessage" class="mb-0"></p>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="resetAnonymizer()">
                            <i class="fas fa-redo me-2"></i>
                            Tentar Novamente
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($cpfFindings) || ($cpfData['total_resources'] ?? 0) > 0): ?>
    <!-- Estatísticas -->
    <div class="cpf-stats-section mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="stat-card bg-danger">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= number_format($cpfData['total_resources'] ?? 0, 0, ',', '.') ?></h3>
                        <p>Recursos com CPFs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-warning">
                    <div class="stat-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= number_format($estatisticas['total'] ?? 0, 0, ',', '.') ?></h3>
                        <p>Total de CPFs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-info">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $lastScanInfo ? date('H:i', strtotime($lastScanInfo['lastScan'])) : '--:--' ?></h3>
                        <p>Última Verificação</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Resultados -->
    <div class="cpf-results-section">
        <div class="card">
            <div class="card-header">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                    <div class="d-flex align-items-center">
                        <h5 class="card-title mb-0 me-3">
                            <i class="fas fa-list icon"></i>
                            Recursos com CPFs Detectados
                        </h5>
                        <span id="contadorRecursos" class="badge bg-secondary">
                            <?= number_format($cpfData['total_resources'] ?? 0, 0, ',', '.') ?> recursos
                        </span>
                    </div>
                    <div class="d-flex gap-2 w-100 w-md-auto">
                        <!-- Filtro por Órgão -->
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" id="filtroOrgaoDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-filter me-1"></i>
                                <span id="orgaoSelecionadoText">Todos os órgãos</span>
                                <span id="contadorOrgao" class="badge bg-info ms-1 d-none">0</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filtroOrgaoDropdown" style="max-height: 400px; overflow-y: auto;">
                                <li>
                                    <a class="dropdown-item" href="#" onclick="selecionarOrgao('', 'Todos os órgãos'); return false;">
                                        <i class="fas fa-globe me-2"></i>Todos os órgãos
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php
                                // Buscar lista de órgãos únicos
                                try {
                                    if ($pdo) {
                                        $stmtOrgaos = $pdo->query("
                                            SELECT orgao, COUNT(*) as total 
                                            FROM mpda_recursos_com_cpf 
                                            GROUP BY orgao 
                                            ORDER BY orgao
                                        ");
                                        $orgaos = $stmtOrgaos->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($orgaos as $orgao):
                                ?>
                                    <li>
                                        <?php 
                                            $orgaoDisplayName = strlen($orgao['orgao']) > 50 ? substr($orgao['orgao'], 0, 50) . '...' : $orgao['orgao'];
                                        ?>
                                        <a class="dropdown-item" href="#" onclick="selecionarOrgao('<?= htmlspecialchars($orgao['orgao'], ENT_QUOTES) ?>', '<?= htmlspecialchars($orgao['orgao'], ENT_QUOTES) ?>', <?= $orgao['total'] ?>); return false;" title="<?= htmlspecialchars($orgao['orgao']) ?>">
                                            <i class="fas fa-building me-2 text-primary"></i>
                                            <span class="orgao-dropdown-text"><?= htmlspecialchars($orgaoDisplayName) ?></span>
                                            <span class="badge bg-secondary ms-2"><?= $orgao['total'] ?></span>
                                        </a>
                                    </li>
                                <?php
                                        endforeach;
                                    }
                                } catch (Exception $e) {
                                    error_log("Erro ao buscar órgãos: " . $e->getMessage());
                                }
                                ?>
                            </ul>
                        </div>
                        
                        <!-- Botão Limpar Filtro -->
                        <button id="btnLimparFiltro" class="btn btn-outline-secondary" onclick="limparFiltro(); return false;" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                        
                        <!-- Botão Exportar -->
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="export_cpf_excel">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-file-excel me-1"></i>
                                <span class="d-none d-md-inline">Exportar</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if (!empty($cpfFindings)): ?>
                        <table class="table table-hover cpf-table-compact mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Dataset</th>
                                    <th style="width: 15%;">Órgão</th>
                                    <th style="width: 25%;">Recurso</th>
                                    <th style="width: 8%;" class="text-center">Formato</th>
                                    <th style="width: 8%;" class="text-center">CPFs</th>
                                    <th style="width: 12%;">Verificado</th>
                                    <th style="width: 7%;" class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cpfFindings as $index => $finding): 
                                    $datasetName = strlen($finding['dataset_name']) > 40 ? substr($finding['dataset_name'], 0, 40) . '...' : $finding['dataset_name'];
                                    $resourceName = strlen($finding['resource_name']) > 35 ? substr($finding['resource_name'], 0, 35) . '...' : $finding['resource_name'];
                                    $orgaoName = strlen($finding['dataset_organization']) > 20 ? substr($finding['dataset_organization'], 0, 20) . '...' : $finding['dataset_organization'];
                                    $datasetId = substr($finding['dataset_id'], 0, 8) . '...';
                                ?>
                                    <tr>
                                        <td class="compact-cell">
                                            <div class="cell-content">
                                                <strong class="dataset-name" title="<?= htmlspecialchars($finding['dataset_name']) ?>">
                                                    <?= htmlspecialchars($datasetName) ?>
                                                </strong>
                                                <small class="text-muted d-block">ID: <?= htmlspecialchars($datasetId) ?></small>
                                            </div>
                                        </td>
                                        <td class="compact-cell">
                                            <span class="badge bg-primary orgao-badge-compact" title="<?= htmlspecialchars($finding['dataset_organization']) ?>">
                                                <i class="fas fa-building me-1"></i>
                                                <?= htmlspecialchars($orgaoName) ?>
                                            </span>
                                        </td>
                                        <td class="compact-cell">
                                            <div class="cell-content">
                                                <strong class="resource-name" title="<?= htmlspecialchars($finding['resource_name']) ?>">
                                                    <?= htmlspecialchars($resourceName) ?>
                                                </strong>
                                                <div class="resource-actions mt-1">
                                                    <a href="<?= htmlspecialchars($finding['resource_url']) ?>" target="_blank" class="text-decoration-none">
                                                        <small><i class="fas fa-external-link-alt"></i> Ver</small>
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary format-badge"><?= strtoupper($finding['resource_format']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger cpf-badge"><?= $finding['cpf_count'] ?></span>
                                        </td>
                                        <td class="compact-cell">
                                            <small class="text-muted"><?= date('d/m/y', strtotime($finding['last_checked'])) ?><br><?= date('H:i', strtotime($finding['last_checked'])) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex gap-1 justify-content-center">
                                                <button class="btn btn-outline-info btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>" aria-expanded="false" 
                                                        title="Ver CPFs detectados">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <div class="table-dropdown">
                                                    <button class="btn btn-outline-primary btn-sm" type="button" title="Ações">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <button class="dropdown-item" type="button" onclick="return gerarRelatorioCpf('<?= htmlspecialchars($finding['resource_id']) ?>', '<?= htmlspecialchars($finding['resource_name']) ?>', event);">
                                                            <i class="fas fa-file-excel me-2 text-success"></i> Gerar Relatório
                                                        </button>
                                                        <?php if (!empty($finding['dataset_url']) && $finding['dataset_url'] !== '#'): ?>
                                                            <hr class="dropdown-divider">
                                                            <a class="dropdown-item" href="<?= htmlspecialchars($finding['dataset_url']) ?>" target="_blank">
                                                                <i class="fas fa-external-link-alt me-2 text-primary"></i> Ver Dataset
                                                            </a>
                                                        <?php endif; ?>
                                                        <hr class="dropdown-divider">
                                                        <button class="dropdown-item text-danger" type="button" onclick="excluirRecurso('<?= htmlspecialchars($finding['resource_id']) ?>', '<?= htmlspecialchars($finding['resource_name']) ?>'); return false;">
                                                            <i class="fas fa-trash me-2"></i> Excluir
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="7" class="p-0">
                                            <div class="collapse" id="collapse<?= $index ?>">
                                                <div class="p-3 bg-light">
                                                    <h6 class="mb-3">
                                                        <i class="fas fa-id-card icon"></i>
                                                        CPFs Encontrados (<?= $finding['cpf_count'] ?>)
                                                    </h6>
                                                    <div class="cpf-list">
                                                        <?php 
                                                        $cpfs = is_array($finding['cpfs']) ? $finding['cpfs'] : json_decode($finding['cpfs'], true);
                                                        if (is_array($cpfs)):
                                                            foreach ($cpfs as $cpf): 
                                                        ?>
                                                            <span class="cpf-item"><?= htmlspecialchars($cpf) ?></span>
                                                        <?php 
                                                            endforeach;
                                                        endif;
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="text-center p-5">
                            <i class="fas fa-inbox text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                            <h5 class="mt-3 text-muted">Nenhum CPF encontrado</h5>
                            <p class="text-muted">Execute a análise CKAN para verificar os recursos.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($cpfData['total_paginas'] > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Navegação da página de CPFs">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= !$cpfData['has_prev'] ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=cpf&page=<?= $paginaCpfAtual - 1 ?>">Anterior</a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $cpfData['total_paginas']; $i++): ?>
                                <li class="page-item <?= $i === $paginaCpfAtual ? 'active' : '' ?>" aria-current="page">
                                    <a class="page-link" href="?tab=cpf&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= !$cpfData['has_next'] ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=cpf&page=<?= $paginaCpfAtual + 1 ?>">Próximo</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
