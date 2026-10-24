<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Controllers/SimulacaoController.php';
require_once __DIR__ . '/../src/Utils/Database.php';

if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

$user = Auth::user();
$pdo = Database::getConnection();

// Determinar escopo dos dados baseado nas permissões
$isAdmin = Auth::isAdmin();
$isSuperAdmin = Auth::isSuperAdmin();

if ($isSuperAdmin) {
    // SuperAdmin vê TODOS os dados
    $userFilter = '';
    $userParams = [];
    $scopeTitle = 'Relatórios Globais do Sistema';
} elseif ($isAdmin) {
    // Admin vê todos EXCETO SuperAdmins
    $userFilter = 'AND u.user_type != ?';
    $userParams = ['superadmin'];
    $scopeTitle = 'Relatórios Administrativos';
} else {
    // Usuário normal vê apenas seus dados
    $userFilter = 'AND s.user_id = ?';
    $userParams = [$user['id']];
    $scopeTitle = 'Meus Relatórios';
}

// Consulta otimizada única para simulações com dados agregados
$sqlSimulacoes = "
    SELECT 
        s.id, s.nome, s.tipo_obra, s.destinacao, s.uf, s.responsavel, 
        s.regime_tributario, s.area, s.valor_inss, s.criado_em,
        u.nome as usuario_nome, u.user_type,
        (SELECT COUNT(*) FROM planejamento_tributario pt WHERE pt.simulacao_id = s.id) as has_planejamento
    FROM simulacoes s 
    INNER JOIN users u ON s.user_id = u.id 
    WHERE 1=1 $userFilter
    ORDER BY s.criado_em DESC
";

$stmt = $pdo->prepare($sqlSimulacoes);
$stmt->execute($userParams);
$simulacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estatísticas básicas
$totalSimulacoes = count($simulacoes);
$totalINSS = array_sum(array_column($simulacoes, 'valor_inss'));
$mediaINSS = $totalSimulacoes > 0 ? $totalINSS / $totalSimulacoes : 0;



// Estatísticas de planejamentos (consulta otimizada)
$sqlPlanejamentos = "
    SELECT 
        COUNT(pt.id) as total_planejamentos,
        COUNT(DISTINCT pt.simulacao_id) as simulacoes_com_planejamento,
        AVG(pt.rmt_base) as media_rmt_base,
        SUM(pt.rmt_base) as total_rmt_base
    FROM planejamento_tributario pt
    INNER JOIN simulacoes s ON pt.simulacao_id = s.id
    INNER JOIN users u ON s.user_id = u.id
    WHERE 1=1 $userFilter
";

$stmt = $pdo->prepare($sqlPlanejamentos);
$stmt->execute($userParams);
$estatisticasPlanejamentos = $stmt->fetch(PDO::FETCH_ASSOC);

$totalPlanejamentos = $estatisticasPlanejamentos['total_planejamentos'] ?? 0;
$simulacoesComPlanejamento = $estatisticasPlanejamentos['simulacoes_com_planejamento'] ?? 0;
$taxaConversao = $totalSimulacoes > 0 ? ($simulacoesComPlanejamento / $totalSimulacoes) * 100 : 0;
$mediaRmtBase = $estatisticasPlanejamentos['media_rmt_base'] ?? 0;
$totalRmtBase = $estatisticasPlanejamentos['total_rmt_base'] ?? 0;

// Classificação de Valor de Negócio baseado na Taxa de Conversão
$valorNegocio = 'Sem Dados';
$corValorNegocio = 'secondary';
$iconeValorNegocio = 'chart-line';
$corIconeValorNegocio = 'text-gray-300';
$sombra = '';

if ($totalSimulacoes > 0) {
    if ($taxaConversao >= 50) {
        $valorNegocio = 'Alto';
        $corValorNegocio = 'success';
        $iconeValorNegocio = 'arrow-up';
        $corIconeValorNegocio = 'text-success';
        $sombra = 'shadow-success-subtle';
        $bordaColorida = 'border-success';
    } elseif ($taxaConversao >= 20) {
        $valorNegocio = 'Médio';
        $corValorNegocio = 'warning';
        $iconeValorNegocio = 'minus';
        $corIconeValorNegocio = 'text-warning';
        $sombra = 'shadow-warning-subtle';
        $bordaColorida = 'border-warning';
    } else {
        $valorNegocio = 'Baixo';
        $corValorNegocio = 'danger';
        $iconeValorNegocio = 'arrow-down';
        $corIconeValorNegocio = 'text-danger';
        $sombra = 'shadow-danger-subtle';
        $bordaColorida = 'border-danger';
    }
} else {
    $bordaColorida = '';
}

// Estatísticas de usuários (apenas para admins)
$estatisticasUsuarios = null;
if ($isAdmin) {
    $sqlUsuarios = "
        SELECT 
            COUNT(*) as total_usuarios,
            SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as usuarios_ativos,
            SUM(CASE WHEN user_type = 'lead' THEN 1 ELSE 0 END) as total_leads,
            SUM(CASE WHEN user_type = 'client' THEN 1 ELSE 0 END) as total_clients,
            SUM(CASE WHEN user_type = 'admin' THEN 1 ELSE 0 END) as total_admins" .
            ($isSuperAdmin ? ", SUM(CASE WHEN user_type = 'superadmin' THEN 1 ELSE 0 END) as total_superadmins" : "") . "
        FROM users 
        WHERE 1=1" . ($isAdmin && !$isSuperAdmin ? " AND user_type != 'superadmin'" : "");
    
    $stmt = $pdo->prepare($sqlUsuarios);
    $stmt->execute();
    $estatisticasUsuarios = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Dados por destinação
$dadosPorDestinacao = [];
foreach ($simulacoes as $sim) {
    $destinacao = $sim['destinacao'];
    if (!isset($dadosPorDestinacao[$destinacao])) {
        $dadosPorDestinacao[$destinacao] = ['count' => 0, 'total_inss' => 0, 'total_area' => 0];
    }
    $dadosPorDestinacao[$destinacao]['count']++;
    $dadosPorDestinacao[$destinacao]['total_inss'] += $sim['valor_inss'];
    $dadosPorDestinacao[$destinacao]['total_area'] += $sim['area'];
}

// Dados por mês (últimos 6 meses)
$dadosPorMes = [];
for ($i = 5; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $dadosPorMes[$mes] = ['count' => 0, 'total_inss' => 0];
}

foreach ($simulacoes as $sim) {
    $mes = date('Y-m', strtotime($sim['criado_em']));
    if (isset($dadosPorMes[$mes])) {
        $dadosPorMes[$mes]['count']++;
        $dadosPorMes[$mes]['total_inss'] += $sim['valor_inss'];
    }
}

// Dados por UF
$dadosPorUF = [];
foreach ($simulacoes as $sim) {
    $uf = $sim['uf'];
    if (!isset($dadosPorUF[$uf])) {
        $dadosPorUF[$uf] = ['count' => 0, 'total_inss' => 0];
    }
    $dadosPorUF[$uf]['count']++;
    $dadosPorUF[$uf]['total_inss'] += $sim['valor_inss'];
}

// Ordenar por quantidade
arsort($dadosPorUF);
$dadosPorUF = array_slice($dadosPorUF, 0, 10, true); // Top 10 UFs

if (!isset($config)) {
    require_once __DIR__ . '/../src/Utils/LocalConfig.php';
    $config = LocalConfig::getAll();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Calculadora INSS Obra Legal ®</title>
    <?php
    require_once __DIR__ . '/../src/Utils/LocalConfig.php';
    $config = LocalConfig::getAll();
    $baseUrl = LocalConfig::getBaseUrl();

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
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-dark" id="sidebar-wrapper">
            <div class="sidebar-content-wrapper">
                <div class="sidebar-heading text-center py-4">
                    <?php if (!empty($logoFile)): ?>
                        <img class="sidebar-logo" src="<?php echo LocalConfig::buildAssetUrl($logoFile); ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <span class="text-white fw-bold fs-5"><?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;"><i class="fas fa-tachometer-alt fa-fw me-2"></i><span class="sidebar-text">Dashboard</span></a>
                    <a href="nova_simulacao.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;"><i class="fas fa-plus fa-fw me-2"></i><span class="sidebar-text">Nova Simulação</span></a>
                    <a href="minhas_simulacoes.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;"><i class="fas fa-list-alt fa-fw me-2"></i><span class="sidebar-text">Minhas Simulações</span></a>
                    <a href="relatorios.php" class="list-group-item list-group-item-action bg-dark text-white active" onclick="window.location=this.href;return false;"><i class="fas fa-chart-bar fa-fw me-2"></i><span class="sidebar-text">Relatórios</span></a>
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800"><?php echo $scopeTitle; ?></h1>
                    <div>
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Imprimir
                        </button>
                    </div>
                </div>

                <!-- Cards de Resumo Geral -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1"><?= $isAdmin ? 'Total de Simulações' : 'Minhas Simulações' ?></div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalSimulacoes ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-calculator fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total INSS</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ <?= number_format($totalINSS, 2, ',', '.'); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Média INSS</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ <?= number_format($mediaINSS, 2, ',', '.'); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-chart-line fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($isAdmin): ?>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-<?= $corValorNegocio ?> <?= $bordaColorida ?> shadow <?= $sombra ?> h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-<?= $corValorNegocio ?> text-uppercase mb-1">Valor de Negócio</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $valorNegocio ?></div>
                                        <div class="text-xs text-muted">Taxa: <?= number_format($taxaConversao, 1) ?>%</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-<?= $iconeValorNegocio ?> fa-2x <?= $corIconeValorNegocio ?>"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Destinações</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($dadosPorDestinacao) ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-map-marker-alt fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($isAdmin): ?>
                <!-- Cards de Planejamentos Tributários (Apenas Admins) -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-secondary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Planejamentos Criados</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalPlanejamentos ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-balance-scale fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-<?= $corValorNegocio ?> shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-<?= $corValorNegocio ?> text-uppercase mb-1">Taxa de Conversão</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($taxaConversao, 1) ?>%</div>
                                        <div class="text-xs text-muted"><?= $simulacoesComPlanejamento ?> de <?= $totalSimulacoes ?> simulações</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-percentage fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">RMT Base Média</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ <?= number_format($mediaRmtBase, 2, ',', '.') ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-chart-line fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">RMT Base Total</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ <?= number_format($totalRmtBase, 2, ',', '.') ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-coins fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($isAdmin && $estatisticasUsuarios): ?>
                <!-- Cards de Usuários (Apenas Admins) -->
                <div class="row mb-4">
                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card border-left-dark shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Total Usuários</div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800"><?= $estatisticasUsuarios['total_usuarios'] ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-users fa-lg text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Ativos</div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800"><?= $estatisticasUsuarios['usuarios_ativos'] ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-user-check fa-lg text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Leads</div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800"><?= $estatisticasUsuarios['total_leads'] ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-user fa-lg text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Clientes</div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800"><?= $estatisticasUsuarios['total_clients'] ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-user-tie fa-lg text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Admins</div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800"><?= $estatisticasUsuarios['total_admins'] ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-user-shield fa-lg text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($isSuperAdmin): ?>
                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">SuperAdmins</div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800"><?= $estatisticasUsuarios['total_superadmins'] ?? 0 ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-crown fa-lg text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Gráficos -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Simulações por Mês</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartPorMes" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Distribuição por Destinação</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartPorDestinacao" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabelas de Dados -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Resumo por Destinação</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Destinação</th>
                                                <th>Qtd</th>
                                                <th>Total INSS</th>
                                                <th>Média INSS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dadosPorDestinacao as $destinacao => $dados): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($destinacao) ?></td>
                                                <td><?= $dados['count'] ?></td>
                                                <td>R$ <?= number_format($dados['total_inss'], 2, ',', '.') ?></td>
                                                <td>R$ <?= number_format($dados['total_inss'] / $dados['count'], 2, ',', '.') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Top 10 Estados (UF)</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>UF</th>
                                                <th>Qtd</th>
                                                <th>Total INSS</th>
                                                <th>Média INSS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dadosPorUF as $uf => $dados): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($uf) ?></strong></td>
                                                <td><?= $dados['count'] ?></td>
                                                <td>R$ <?= number_format($dados['total_inss'], 2, ',', '.') ?></td>
                                                <td>R$ <?= number_format($dados['total_inss'] / $dados['count'], 2, ',', '.') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Simulações Recentes -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Últimas 10 Simulações</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>UF</th>
                                        <th>Área</th>
                                        <th>Valor INSS</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($simulacoes, 0, 10) as $sim): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sim['nome']) ?></td>
                                        <td><?= htmlspecialchars($sim['tipo_obra']) ?></td>
                                        <td><?= htmlspecialchars($sim['uf']) ?></td>
                                        <td><?= number_format($sim['area'], 2, ',', '.') ?> m²</td>
                                        <td>R$ <?= number_format($sim['valor_inss'], 2, ',', '.') ?></td>
                                        <td><?= date('d/m/Y', strtotime($sim['criado_em'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <?php include 'footer_simple.php'; ?>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Dados para os gráficos
    var dadosPorMes = <?php echo json_encode($dadosPorMes); ?>;
    var dadosPorDestinacao = <?php echo json_encode($dadosPorDestinacao); ?>;

    // Gráfico de Simulações por Mês
    var ctx1 = document.getElementById('chartPorMes').getContext('2d');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: Object.keys(dadosPorMes).map(function(mes) {
                return new Date(mes + '-01').toLocaleDateString('pt-BR', {month: 'short', year: 'numeric'});
            }),
            datasets: [{
                label: 'Simulações',
                data: Object.values(dadosPorMes).map(function(item) { return item.count; }),
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Evolução de Simulações por Mês'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Gráfico de Distribuição por Destinação
    var ctx2 = document.getElementById('chartPorDestinacao').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: Object.keys(dadosPorDestinacao),
            datasets: [{
                data: Object.values(dadosPorDestinacao).map(function(item) { return item.count; }),
                backgroundColor: [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)',
                    'rgba(199, 199, 199, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Distribuição por Destinação'
                },
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
</script>

<style>
/* Sombras coloridas sutis para os cards Valor de Negócio */
.shadow-success-subtle {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(40, 167, 69, 0.15) !important;
}

.shadow-warning-subtle {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(255, 193, 7, 0.15) !important;
}

.shadow-danger-subtle {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(220, 53, 69, 0.15) !important;
}

/* Bordas coloridas mais destacadas */
.card.border-success {
    border: 2px solid rgba(40, 167, 69, 0.3) !important;
    border-left: 4px solid #28a745 !important;
}

.card.border-warning {
    border: 2px solid rgba(255, 193, 7, 0.3) !important;
    border-left: 4px solid #ffc107 !important;
}

.card.border-danger {
    border: 2px solid rgba(220, 53, 69, 0.3) !important;
    border-left: 4px solid #dc3545 !important;
}

/* Hover effects para realçar ainda mais */
.card.shadow-success-subtle:hover {
    box-shadow: 0 0.25rem 2rem 0 rgba(40, 167, 69, 0.25) !important;
    transform: translateY(-2px);
    transition: all 0.2s ease-in-out;
}

.card.shadow-warning-subtle:hover {
    box-shadow: 0 0.25rem 2rem 0 rgba(255, 193, 7, 0.25) !important;
    transform: translateY(-2px);
    transition: all 0.2s ease-in-out;
}

.card.shadow-danger-subtle:hover {
    box-shadow: 0 0.25rem 2rem 0 rgba(220, 53, 69, 0.25) !important;
    transform: translateY(-2px);
    transition: all 0.2s ease-in-out;
}

/* Hover nas bordas coloridas */
.card.border-success:hover {
    border: 2px solid rgba(40, 167, 69, 0.5) !important;
    border-left: 4px solid #28a745 !important;
}

.card.border-warning:hover {
    border: 2px solid rgba(255, 193, 7, 0.5) !important;
    border-left: 4px solid #ffc107 !important;
}

.card.border-danger:hover {
    border: 2px solid rgba(220, 53, 69, 0.5) !important;
    border-left: 4px solid #dc3545 !important;
}
</style>

</body>
</html>

