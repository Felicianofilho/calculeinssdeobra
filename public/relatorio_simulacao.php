<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Models/Simulacao.php';
require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Utils/CalculoINSS.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';

// Carregar configurações e definir base URL
$config = LocalConfig::getAll();
$baseUrl = LocalConfig::getBaseUrl();

if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

$user = Auth::user();
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: minhas_simulacoes.php?error=not_found');
    exit;
}

$simulacao = Simulacao::find($id);

if (!$simulacao || ($simulacao['user_id'] != $user['id'] && !Auth::isAdmin())) {
    header('Location: minhas_simulacoes.php?error=forbidden');
    exit;
}

// Cálculo do INSS
$dados_simulacao = $simulacao;
if (isset($simulacao['criado_em'])) {
    $dados_simulacao['data_simulacao'] = $simulacao['criado_em'];
}
$dados_calculo = CalculoINSS::calcular($dados_simulacao);

// Buscar informações do VAU para exibição
$pdo = Database::getConnection();
$mes = null;
$ano = null;
if (isset($simulacao['criado_em'])) {
    $dt = $simulacao['criado_em'];
    $mes = (int)date('n', strtotime($dt));
    $ano = (int)date('Y', strtotime($dt));
    $stmt = $pdo->prepare("SELECT valor, mes, ano FROM vau WHERE uf = ? AND destinacao = ? AND (ano < ? OR (ano = ? AND mes <= ?)) ORDER BY ano DESC, mes DESC LIMIT 1");
    $stmt->execute([$simulacao['uf'], $simulacao['destinacao'], $ano, $ano, $mes]);
    $vau_info = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT valor, mes, ano FROM vau WHERE uf = ? AND destinacao = ? ORDER BY ano DESC, mes DESC LIMIT 1");
    $stmt->execute([$simulacao['uf'], $simulacao['destinacao']]);
    $vau_info = $stmt->fetch();
}

// Função para gerar descrição da área equivalente
function getDescricaoAreaEquivalente($area, $destinacao, $percentual) {
    $descricao = number_format($percentual, 1, ',', '.') . "% área";
    
    if ($destinacao == "Res. Unifamiliar") {
        if ($area <= 1000) {
            $descricao .= " até 1000m²";
        } else {
            $descricao .= " acima de 1000m²";
        }
    } elseif ($destinacao == "Res. Multifamiliar") {
        if ($area <= 1000) {
            $descricao .= " até 1000m²";
        } else {
            $descricao .= " acima de 1000m²";
        }
    } elseif ($destinacao == "Comercial Salas/Lojas") {
        if ($area <= 3000) {
            $descricao .= " até 3000m²";
        } else {
            $descricao .= " acima de 3000m²";
        }
    } elseif ($destinacao == "Galpão Ind.") {
        $descricao .= " (Galpão Industrial)";
    } elseif ($destinacao == "Casa Popular") {
        $descricao .= " (Casa Popular)";
    } elseif ($destinacao == "Conj. Hab. Popular") {
        $descricao .= " (Conjunto Habitacional Popular)";
    } else {
        $descricao .= " (sem fator de equivalência)";
    }
    
    return $descricao;
}

// Função para gerar descrição do tipo de obra
function getDescricaoTipoObra($tipo_obra, $percentual) {
    if ($tipo_obra == 'Alvenaria') {
        return "Alvenaria (" . number_format($percentual, 1, ',', '.') . "%)";
    } elseif ($tipo_obra == 'Madeira') {
        return "Madeira (" . number_format($percentual, 1, ',', '.') . "%)";
    } elseif ($tipo_obra == 'Mista') {
        return "Mista (" . number_format($percentual, 1, ',', '.') . "%)";
    }
    return $tipo_obra . " (" . number_format($percentual, 1, ',', '.') . "%)";
}

// Função para gerar descrição da alíquota
function getDescricaoAliquota($responsavel, $regime_tributario, $aliquota) {
    if ($responsavel == 'Pessoa Física') {
        return "Pessoa Física (" . number_format($aliquota, 1, ',', '.') . "%)";
    } else {
        if ($regime_tributario == 'Simples Nacional') {
            return "Pessoa Jurídica - Simples Nacional (" . number_format($aliquota, 1, ',', '.') . "%)";
        } elseif ($regime_tributario == 'Lucro Presumido') {
            return "Pessoa Jurídica - Lucro Presumido (" . number_format($aliquota, 1, ',', '.') . "%)";
        } elseif ($regime_tributario == 'Lucro Real') {
            return "Pessoa Jurídica - Lucro Real (" . number_format($aliquota, 1, ',', '.') . "%)";
        }
        return "Pessoa Jurídica (" . number_format($aliquota, 1, ',', '.') . "%)";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Simulação - Calculadora INSS Obra Legal ®</title>
    <?php
    $logoFile = $config['theme']['branding']['logo_file'] ?? '';
    $logoFilePath = !empty($logoFile) ? __DIR__ . '/' . $logoFile : '';
    $logoVersion = ($logoFilePath && file_exists($logoFilePath) ? filemtime($logoFilePath) : time());

    $faviconFile = $config['theme']['branding']['favicon_file'] ?? '';
    $faviconFilePath = !empty($faviconFile) ? __DIR__ . '/' . $faviconFile : '';
    $faviconVersion = ($faviconFilePath && file_exists($faviconFilePath) ? filemtime($faviconFilePath) : time());
    ?>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo LocalConfig::buildAssetUrl($faviconFile); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <?php echo LocalConfig::getCustomCSS(); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @media print { .no-print { display: none !important; } }
        body { background-color: #f8f9fa; }
        .card { border: none; }
        .card-header { background-color: #007bff; color: white; }
        .sidebar-heading { background-color: #343a40; }

        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
            flex-direction: column;
        }
        .spinner {
            border: 8px solid #f3f3f3; 
            border-top: 8px solid #3498db; 
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 2s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    </head>
<body>
    <div id="loading-overlay">
        <div class="spinner"></div>
        <h4 class="mt-3">Gerando PDF...</h4>
    </div>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-dark no-print" id="sidebar-wrapper">
            <div class="sidebar-content-wrapper">
                <div class="sidebar-heading text-center py-4">
                    <?php if (!empty($config['theme']['branding']['logo_file'])): ?>
                        <img class="sidebar-logo" src="<?php echo LocalConfig::buildAssetUrl($config['theme']['branding']['logo_file']); ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <span class="text-white fw-bold fs-5"><?php echo $config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'; ?></span>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;"><i class="fas fa-tachometer-alt fa-fw me-2"></i><span class="sidebar-text">Dashboard</span></a>
                    <a href="nova_simulacao.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;"><i class="fas fa-plus fa-fw me-2"></i><span class="sidebar-text">Nova Simulação</span></a>
                    <a href="minhas_simulacoes.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;"><i class="fas fa-list-alt fa-fw me-2"></i><span class="sidebar-text">Minhas Simulações</span></a>
                    <?php if (Auth::isAdmin()): ?>
                    <a href="admin.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;"><i class="fas fa-user-shield fa-fw me-2"></i><span class="sidebar-text">Admin</span></a>
                    <?php endif; ?>
                    <a href="logout.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;"><i class="fas fa-sign-out-alt fa-fw me-2"></i><span class="sidebar-text">Sair</span></a>
                </div>
            </div>
            <div class="sidebar-toggler-wrapper">
                <button class="sidebar-toggler" id="sidebar-toggle-btn">
                    <i class="fas fa-angle-double-left"></i>
                </button>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom">
                <div class="container-fluid">
                    <div class="navbar-nav ms-auto">
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($user['nome']); ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="perfil.php">Meu Perfil</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php">Sair</a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-alt me-2"></i>Relatório de Simulação</h1>
                    <div>
                        <?php if (($simulacao['responsavel'] ?? '') === 'Pessoa Física'): ?>
                        <a href="planejamento_tributario.php?simulacao_id=<?php echo urlencode($id ?? ''); ?>" class="btn btn-success me-2">
                            <i class="fas fa-calculator me-2"></i>Planejamento Tributário
                        </a>
                        <?php endif; ?>
                        <a href="#" onclick="gerarPDF(event, 'relatorio_simulacao_pdf.php?id=<?php echo $id; ?>')" class="btn btn-primary"><i class="fas fa-file-pdf me-2"></i>Gerar PDF</a>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.history.back();"><i class="fas fa-arrow-left me-2"></i>Voltar</button>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white py-3"><h6 class="m-0 font-weight-bold"><i class="fas fa-info-circle me-2"></i>Informações da Simulação</h6></div>
                    <div class="card-body">
                        <div class="row">
                             <div class="col-md-6">
                                 <p class="mb-1"><strong>Nome da Simulação:</strong> <?php echo htmlspecialchars($simulacao['nome'] ?? 'N/A'); ?></p>
                                 <p class="mb-1"><strong>Responsável:</strong> <?php echo htmlspecialchars($simulacao['responsavel'] ?? 'N/A'); ?></p>
                                 <?php if ($simulacao['responsavel'] === 'Pessoa Jurídica' && !empty($simulacao['regime_tributario'])): ?>
                                 <p class="mb-1"><strong>Regime Tributário:</strong> <?php echo htmlspecialchars($simulacao['regime_tributario']); ?></p>
                                 <?php endif; ?>
                                 <p class="mb-1"><strong>Destinação:</strong> <?php echo htmlspecialchars($simulacao['destinacao'] ?? 'N/A'); ?></p>
                                 <p class="mb-1"><strong>UF:</strong> <?php echo htmlspecialchars($simulacao['uf'] ?? 'N/A'); ?></p>
                             </div>
                             <div class="col-md-6">
                                 <p class="mb-1"><strong>Tipo de Obra:</strong> <?php echo htmlspecialchars($simulacao['tipo_obra'] ?? 'N/A'); ?></p>
                                 <p class="mb-1"><strong>Área:</strong> <?php echo number_format($simulacao['area'] ?? 0, 2, ',', '.'); ?> m²</p>
                                 <p class="mb-1"><strong>Data da Simulação:</strong> <?php echo isset($simulacao['criado_em']) ? date('d/m/Y H:i', strtotime($simulacao['criado_em'])) : 'N/A'; ?></p>
                             </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white py-3"><h6 class="m-0 font-weight-bold"><i class="fas fa-calculator me-2"></i>Detalhes do Cálculo</h6></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-bordered">
                                    <tr>
                                        <td><strong>VAU (Valor Unitário):</strong></td>
                                        <td>
                                            R$ <?php echo number_format($dados_calculo['vau'] ?? 0, 2, ',', '.'); ?>
                                            <?php if ($vau_info): ?>
                                                <br><small class="text-muted">
                                                    <?php 
                                                    $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                                                    echo $meses[$vau_info['mes']] . '/' . $vau_info['ano'] . ' - ' . $simulacao['uf'];
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Área Equivalente:</strong></td>
                                        <td>
                                            <?php echo number_format($dados_calculo['area_equivalente'] ?? 0, 2, ',', '.'); ?> m²
                                            <br><small class="text-muted">
                                                <?php echo getDescricaoAreaEquivalente($simulacao['area'], $simulacao['destinacao'], $dados_calculo['percentual_equivalencia']); ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <tr><td><strong>COD (Custo da Obra):</strong></td><td>R$ <?php echo number_format($dados_calculo['cod'] ?? 0, 2, ',', '.'); ?></td></tr>
                                    <tr>
                                        <td><strong>RMT (Base de Cálculo):</strong></td>
                                        <td>
                                            <strong>R$ <?php echo number_format($dados_calculo['rmt'] ?? 0, 2, ',', '.'); ?></strong>
                                            <br><small class="text-muted">
                                                <?php 
                                                if ($simulacao['responsavel'] == 'Pessoa Física') {
                                                    echo "Fator Social " . number_format($dados_calculo['percentual_rmt'], 1, ',', '.') . "% × " . getDescricaoTipoObra($simulacao['tipo_obra'], ($simulacao['tipo_obra'] == 'Alvenaria' ? 20 : 15));
                                                } else {
                                                    echo getDescricaoTipoObra($simulacao['tipo_obra'], $dados_calculo['percentual_rmt']);
                                                }
                                                ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Alíquota Aplicada:</strong></td>
                                        <td>
                                            <strong><?php echo number_format($dados_calculo['aliquota_aplicada'] ?? 0, 1, ',', '.'); ?>%</strong>
                                            <br><small class="text-muted">
                                                <?php echo getDescricaoAliquota($simulacao['responsavel'], $simulacao['regime_tributario'] ?? '', $dados_calculo['aliquota_aplicada'] ?? 0); ?>
                                            </small>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-primary text-white py-3">
                                        <h6 class="m-0 font-weight-bold"><i class="fas fa-percentage me-2"></i>Alíquotas e Fatores Aplicados</h6>
                                    </div>
                                    <table class="table mb-0">
                                        <tr>
                                            <th style="width: 40%;">Alíquota INSS:</th>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo getDescricaoAliquota($simulacao['responsavel'], $simulacao['regime_tributario'], $dados_calculo['aliquota_aplicada']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Tipo de Obra:</th>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo getDescricaoTipoObra($simulacao['tipo_obra'], ($simulacao['tipo_obra'] == 'Alvenaria' ? 20 : 15)); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php if ($simulacao['responsavel'] == 'Pessoa Física'): ?>
                                        <tr>
                                            <th>Fator Social:</th>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <?php echo number_format($dados_calculo['percentual_rmt'], 1, ',', '.'); ?>% (área <?php echo number_format($simulacao['area'], 0, ',', '.'); ?>m²)
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow">
                    <div class="card-header bg-success text-white py-3"><h6 class="m-0 font-weight-bold"><i class="fas fa-check-circle me-2"></i>Resultado Final</h6></div>
                    <div class="card-body text-center">
                        <h2 class="text-success">INSS a Recolher: R$ <?php echo number_format($dados_calculo['inss'] ?? 0, 2, ',', '.'); ?></h2>
                        <p class="text-muted small">Valor calculado conforme a IN RFB Nº 2.021/2021. Este relatório é uma simulação e não substitui a aferição oficial.</p>
                    </div>
                </div>

            </div>
            <?php include 'footer_simple.php'; ?>
        </div>
        <!-- /#page-content-wrapper -->
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle da Sidebar
        var sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
        if (sidebarToggleBtn) {
            sidebarToggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('wrapper').classList.toggle('toggled');
            });
        }

        function gerarPDF(event, url) {
            event.preventDefault();
            const overlay = document.getElementById('loading-overlay');
            overlay.style.display = 'flex';
            
            // Abre o PDF em uma nova aba
            window.open(url, '_blank');

            // Esconde o spinner após 5 segundos, tempo suficiente para o PDF começar a carregar
            setTimeout(function() {
                overlay.style.display = 'none';
            }, 5000);
        }

        // Limpar o overlay se o usuário voltar para a página
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                document.getElementById('loading-overlay').style.display = 'none';
            }
        });
    </script>
</body>
</html>