<h2>
    <i class="fas fa-chart-line icon"></i>
    Análise PINE - Monitoramento de Datasets
</h2>
<p class="description-text">
    Digite a URL do portal CKAN para analisar a atualização dos datasets. Os dados são salvos e exibidos abaixo com filtros avançados.
</p>

<!-- Formulário de Análise -->
<form method="POST" id="analysis-form">
    <input type="hidden" name="action" value="analyze_portal">
    <div class="row g-3">
        <div class="col-12 col-md-8">
            <label for="portal_url" class="form-label">
                <i class="fas fa-link icon"></i> URL do Portal CKAN
            </label>
            <input type="url" class="form-control" id="portal_url" name="portal_url" 
                   placeholder="https://dadosabertos.go.gov.br" 
                   value="<?= htmlspecialchars($portalUrl) ?>" required>
        </div>
        <div class="col-12 col-md-4 d-flex align-items-end">
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

<!-- Dashboard de Estatísticas -->
<div id="pine-dashboard" class="mt-4 pine-section" style="display: none;">
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <i class="fas fa-database icon me-3" style="font-size: 2rem;"></i>
                    <div>
                        <h3 id="total-datasets" class="mb-0">0</h3>
                        <p class="mb-0">Total de Datasets</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stats-card success">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle icon me-3" style="font-size: 2rem;"></i>
                    <div>
                        <h3 id="datasets-atualizados" class="mb-0">0</h3>
                        <p class="mb-0">Atualizados</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle icon me-3" style="font-size: 2rem;"></i>
                    <div>
                        <h3 id="datasets-desatualizados" class="mb-0">0</h3>
                        <p class="mb-0">Desatualizados</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                <div class="d-flex align-items-center">
                    <i class="fas fa-building icon me-3" style="font-size: 2rem;"></i>
                    <div>
                        <h3 id="total-orgaos" class="mb-0">0</h3>
                        <p class="mb-0">Órgãos</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros e Busca -->
<div id="pine-filters" class="mt-4 pine-section" style="display: none;">
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">
                <i class="fas fa-filter icon"></i> Filtros e Busca
            </h5>
            <div class="row g-3">
                <div class="col-12">
                    <label for="search-dataset" class="form-label">
                        <i class="fas fa-search icon"></i> Buscar Dataset
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control" id="search-dataset" 
                               placeholder="Digite o nome ou ID do dataset...">
                        <button class="btn btn-outline-secondary" type="button" id="clear-search">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-12 col-md-6 col-lg-4 mt-4">
                    <label for="filter-organization" class="form-label">
                        <i class="fas fa-building icon"></i> Órgão
                    </label>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle w-100 text-start" type="button" 
                                id="organizationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span id="organization-text">Todos os órgãos</span>
                        </button>
                        <ul class="dropdown-menu w-100" aria-labelledby="organizationDropdown">
                            <li><a class="dropdown-item" href="#" data-value="">Todos os órgãos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <div id="organization-list"></div>
                        </ul>
                    </div>
                    <input type="hidden" id="filter-organization" value="">
                </div>
                
                <div class="col-12 col-md-6 col-lg-4 mt-4">
                    <label for="filter-status" class="form-label">
                        <i class="fas fa-flag icon"></i> Status
                    </label>
                    <div class="btn-group w-100" role="group" aria-label="Status filter">
                        <input type="radio" class="btn-check" name="status-filter" id="status-all" value="" checked>
                        <label class="btn btn-outline-secondary" for="status-all">
                            <i class="fas fa-list"></i> Todos
                        </label>
                        
                        <input type="radio" class="btn-check" name="status-filter" id="status-updated" value="Atualizado">
                        <label class="btn btn-outline-success" for="status-updated">
                            <i class="fas fa-check-circle"></i> Atualizado
                        </label>
                        
                        <input type="radio" class="btn-check" name="status-filter" id="status-outdated" value="Desatualizado">
                        <label class="btn btn-outline-danger" for="status-outdated">
                            <i class="fas fa-exclamation-triangle"></i> Desatualizado
                        </label>
                    </div>
                    <input type="hidden" id="filter-status" value="">
                </div>
                
                <div class="col-12 col-lg-4 d-flex align-items-end ">
                    <button type="button" class="btn btn-outline-secondary w-100" id="clear-filters"
                            data-bs-toggle="tooltip" data-bs-placement="top" title="Limpar todos os filtros aplicados (Esc)">
                        <i class="fas fa-broom icon"></i> Limpar Filtros
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Datasets -->
<div id="pine-datasets" class="mt-4 pine-section" style="display: none;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="mb-0">
                <i class="fas fa-list icon"></i>
                <span id="datasets-title">Lista de Datasets</span>
            </h3>
            <small class="text-muted" id="datasets-info">
                <i class="fas fa-info-circle me-1"></i>
                Exibindo até 10 itens por página
            </small>
        </div>
        <div class="w-100 w-md-auto">
            <form method="POST" class="d-inline w-100">
                <input type="hidden" name="action" value="export_pine_excel">
                <button type="submit" class="btn btn-success w-100 w-md-auto">
                    <i class="fas fa-file-excel icon"></i> Exportar Excel
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                <table class="table table-hover pine-table-compact mb-0" id="datasets-table">
                    <thead class="sticky-top">
                        <tr>
                            <th style="width: 35%;">Dataset</th>
                            <th style="width: 25%;">Órgão</th>
                            <th style="width: 15%;">Última Atualização</th>
                            <th style="width: 12%;">Status</th>
                            <th style="width: 8%;">Recursos</th>
                            <th style="width: 5%;">Link</th>
                        </tr>
                    </thead>
                    <tbody id="datasets-tbody">
                        <!-- Dados carregados via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Paginação dentro do card -->
        <div class="card-footer bg-light">
            <nav id="pine-pagination">
                <!-- Paginação carregada via AJAX -->
            </nav>
        </div>
    </div>
</div>

<!-- Loading -->
<div id="pine-loading" class="text-center py-5 pine-section" style="display: none;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Carregando...</span>
    </div>
    <p class="mt-3">Carregando dados...</p>
</div>

<!-- Mensagem quando não há dados -->
<div id="pine-no-data" class="text-center py-5 pine-section" style="display: none;">
    <i class="fas fa-inbox icon" style="font-size: 3rem; color: #6c757d;"></i>
    <h4 class="mt-3">Nenhum dataset encontrado</h4>
    <p class="text-muted">Execute uma análise para visualizar os datasets do portal.</p>
</div>
