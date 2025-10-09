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
    <div id="download-data" 
         data-file="<?= htmlspecialchars($downloadFileName, ENT_QUOTES, 'UTF-8') ?>" 
         data-path="<?= htmlspecialchars($downloadFile, ENT_QUOTES, 'UTF-8') ?>" 
         style="display: none;">
    </div>
<?php endif; ?>
