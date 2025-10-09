<?php
// Buscar estatísticas gerais do banco de dados
$estatisticasPainel = [
    'total_datasets' => 0,
    'datasets_atualizados' => 0,
    'datasets_desatualizados' => 0,
    'total_recursos' => 0,
    'total_orgaos' => 0,
    'recursos_com_cpf' => 0,
    'total_cpfs' => 0
];

if ($pdo && $pine) {
    try {
        // Estatísticas PINE
        $estatisticasPine = $pine->getEstatisticasGerais($pdo);
        $estatisticasPainel['total_datasets'] = $estatisticasPine['total_datasets'] ?? 0;
        $estatisticasPainel['datasets_atualizados'] = $estatisticasPine['datasets_atualizados'] ?? 0;
        $estatisticasPainel['datasets_desatualizados'] = $estatisticasPine['datasets_desatualizados'] ?? 0;
        $estatisticasPainel['total_recursos'] = $estatisticasPine['total_recursos'] ?? 0;
        $estatisticasPainel['total_orgaos'] = $estatisticasPine['total_orgaos'] ?? 0;
        
        // Estatísticas CPF
        $stmtCpf = $pdo->query("SELECT COUNT(*) as total FROM mpda_recursos_com_cpf");
        $estatisticasPainel['recursos_com_cpf'] = $stmtCpf->fetchColumn() ?: 0;
        
        $stmtTotalCpfs = $pdo->query("SELECT SUM(quantidade_cpfs) as total FROM mpda_recursos_com_cpf");
        $estatisticasPainel['total_cpfs'] = $stmtTotalCpfs->fetchColumn() ?: 0;
        
    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas do painel: " . $e->getMessage());
    }
}

// Calcular percentuais
$percentualAtualizado = $estatisticasPainel['total_datasets'] > 0 
    ? round(($estatisticasPainel['datasets_atualizados'] / $estatisticasPainel['total_datasets']) * 100, 1) 
    : 0;
$percentualDesatualizado = $estatisticasPainel['total_datasets'] > 0 
    ? round(($estatisticasPainel['datasets_desatualizados'] / $estatisticasPainel['total_datasets']) * 100, 1) 
    : 0;
?>

<h2>
    <i class="fas fa-tachometer-alt icon"></i>
    Painel de Insights
</h2>
<p class="description-text">
    Visão geral e insights relevantes sobre os dados do portal de dados abertos.
</p>

<!-- Estatísticas Principais -->
<div class="row g-4 mt-3">
    <!-- Card: Total de Datasets -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #3d6b35 0%, #2d5a27 100%); border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-subtitle mb-2" style="opacity: 0.9;">Total de Datasets</h6>
                        <h2 class="card-title mb-0" style="font-size: 2.5rem; font-weight: 700;">
                            <?= number_format($estatisticasPainel['total_datasets'], 0, ',', '.') ?>
                        </h2>
                        <small style="opacity: 0.9;">&nbsp;</small>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-circle p-3">
                        <i class="fas fa-database" style="font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card: Datasets Atualizados -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-subtitle mb-2" style="opacity: 0.9;">Atualizados</h6>
                        <h2 class="card-title mb-0" style="font-size: 2.5rem; font-weight: 700;">
                            <?= number_format($estatisticasPainel['datasets_atualizados'], 0, ',', '.') ?>
                        </h2>
                        <small style="opacity: 0.9;"><?= $percentualAtualizado ?>% do total</small>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-circle p-3">
                        <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card: Datasets Desatualizados -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-subtitle mb-2" style="opacity: 0.9;">Desatualizados</h6>
                        <h2 class="card-title mb-0" style="font-size: 2.5rem; font-weight: 700;">
                            <?= number_format($estatisticasPainel['datasets_desatualizados'], 0, ',', '.') ?>
                        </h2>
                        <small style="opacity: 0.9;"><?= $percentualDesatualizado ?>% do total</small>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-circle p-3">
                        <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card: Total de Órgãos -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-subtitle mb-2" style="opacity: 0.9;">Órgãos</h6>
                        <h2 class="card-title mb-0" style="font-size: 2.5rem; font-weight: 700;">
                            <?= number_format($estatisticasPainel['total_orgaos'], 0, ',', '.') ?>
                        </h2>
                        <small style="opacity: 0.9;">&nbsp;</small>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-circle p-3">
                        <i class="fas fa-building" style="font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Segunda Linha de Cards -->
<div class="row g-4 mt-2">
    <!-- Card: Total de Recursos -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card" style="border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Total de Recursos</h6>
                        <h3 class="card-title mb-0" style="font-size: 2rem; font-weight: 700; color: #3d6b35;">
                            <?= number_format($estatisticasPainel['total_recursos'], 0, ',', '.') ?>
                        </h3>
                    </div>
                    <div class="bg-light rounded-circle p-3">
                        <i class="fas fa-file-alt text-primary" style="font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card: Recursos com CPF -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card" style="border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Recursos com CPF</h6>
                        <h3 class="card-title mb-0" style="font-size: 2rem; font-weight: 700; color: #dc3545;">
                            <?= number_format($estatisticasPainel['recursos_com_cpf'], 0, ',', '.') ?>
                        </h3>
                    </div>
                    <div class="bg-light rounded-circle p-3">
                        <i class="fas fa-shield-alt text-danger" style="font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card: Total de CPFs -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card" style="border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Total de CPFs Detectados</h6>
                        <h3 class="card-title mb-0" style="font-size: 2rem; font-weight: 700; color: #f59e0b;">
                            <?= number_format($estatisticasPainel['total_cpfs'], 0, ',', '.') ?>
                        </h3>
                    </div>
                    <div class="bg-light rounded-circle p-3">
                        <i class="fas fa-id-card text-warning" style="font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Informações Adicionais -->
<div class="row g-4 mt-3">
    <div class="col-12">
        <div class="card" style="border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div class="card-header" style="background: linear-gradient(135deg, #3d6b35 0%, #2d5a27 100%); color: white;">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Sobre o Painel
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-chart-line me-2"></i>
                            Monitoramento PINE
                        </h6>
                        <p class="text-muted">
                            O sistema PINE monitora a atualização dos datasets do portal de dados abertos, 
                            identificando quais estão atualizados (últimos <?= DIAS_PARA_DESATUALIZADO ?> dias) e quais precisam de atenção.
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-danger mb-3">
                            <i class="fas fa-shield-alt me-2"></i>
                            Auditoria de Segurança
                        </h6>
                        <p class="text-muted">
                            A verificação de CPF identifica possíveis vazamentos de dados pessoais em recursos públicos, 
                            ajudando a garantir a conformidade com a LGPD.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($estatisticasPainel['total_datasets'] == 0): ?>
<div class="alert alert-info mt-4" role="alert">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Nenhum dado disponível ainda.</strong> Execute uma análise PINE para começar a monitorar os datasets do portal.
</div>
<?php endif; ?>
