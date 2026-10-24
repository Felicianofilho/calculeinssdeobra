<?php
session_start();
require_once __DIR__ . '/../update_user_activity.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';
require_once __DIR__ . '/../src/Controllers/AdminController.php';
require_once __DIR__ . '/../src/Controllers/SimulacaoController.php';
require_once __DIR__ . '/../src/Models/Simulacao.php';
require_once __DIR__ . '/../src/Models/User.php';

if (!Auth::canAccessAdminPanel()) {
    header('Location: perfil.php');
    exit;
}

$user = Auth::user();
$usuarios = AdminController::listarUsuarios();
$todasSimulacoes = Simulacao::all();

// --- Lógica para os Cards de Resumo ---
$totalSimulacoesGlobal = count($todasSimulacoes);
$totalUsuariosAtivos = count(array_filter($usuarios, function($u) { return $u['ativo'] == 1; }));
$valorTotalINSS = array_sum(array_column($todasSimulacoes, 'valor_inss'));

$hoje = new DateTime();
$umaSemanaAtras = (new DateTime())->modify('-7 days');
$novasSimulacoes7Dias = 0;
foreach ($todasSimulacoes as $sim) {
    $dataSimulacao = new DateTime($sim['criado_em']);
    if ($dataSimulacao >= $umaSemanaAtras && $dataSimulacao <= $hoje) {
        $novasSimulacoes7Dias++;
    }
}

// --- Lógica para os Gráficos ---

// 1. Gráfico de Linha: Novos Usuários e Simulações por Mês (Últimos 6 meses)
$mesesLabels = [];
$novosUsuariosPorMes = array_fill(0, 6, 0);
$novasSimulacoesPorMes = array_fill(0, 6, 0);

for ($i = 5; $i >= 0; $i--) {
    $dataRef = (clone $hoje)->modify("-$i month");
    $mesesLabels[] = $dataRef->format('M/y');
    $mesAno = $dataRef->format('Y-m');

    foreach ($usuarios as $u) {
        if (date('Y-m', strtotime($u['criado_em'])) == $mesAno) {
            $novosUsuariosPorMes[5 - $i]++;
        }
    }
    foreach ($todasSimulacoes as $sim) {
        if (date('Y-m', strtotime($sim['criado_em'])) == $mesAno) {
            $novasSimulacoesPorMes[5 - $i]++;
        }
    }
}

// 2. Gráfico de Barras: Top 5 UFs
$ufsContagem = array_count_values(array_column($todasSimulacoes, 'uf'));
arsort($ufsContagem);
$top5UFs = array_slice($ufsContagem, 0, 5, true);
$ufsLabels = array_keys($top5UFs);
$ufsData = array_values($top5UFs);

// 3. Gráfico de Pizza: Distribuição por Faixa de Área
$faixasArea = [
    '0-100 m²' => 0, '101-200 m²' => 0, '201-300 m²' => 0, 
    '301-400 m²' => 0, '> 400 m²' => 0
];
foreach ($todasSimulacoes as $sim) {
    $area = floatval($sim['area']);
    if ($area <= 100) $faixasArea['0-100 m²']++;
    elseif ($area <= 200) $faixasArea['101-200 m²']++;
    elseif ($area <= 300) $faixasArea['201-300 m²']++;
    elseif ($area <= 400) $faixasArea['301-400 m²']++;
    else $faixasArea['> 400 m²']++;
}
$faixasAreaLabels = array_keys($faixasArea);
$faixasAreaData = array_values($faixasArea);

// 4. Gráfico de Pizza: Distribuição por Destinação
$destinacoesContagem = array_count_values(array_column($todasSimulacoes, 'destinacao'));
arsort($destinacoesContagem);
$destinacoesLabels = array_keys($destinacoesContagem);
$destinacoesData = array_values($destinacoesContagem);

// Carregar configurações
$config = LocalConfig::getAll();

// Cache-busting para logo e favicon
$baseUrl = LocalConfig::getBaseUrl();
$faviconFile = $config['theme']['branding']['favicon_file'] ?? '';
$logoFile = $config['theme']['branding']['logo_file'] ?? '';

// Usar buildAssetUrl para assets
$logoUrl = LocalConfig::buildAssetUrl($logoFile);
$faviconUrl = LocalConfig::buildAssetUrl($faviconFile);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração - Calculadora INSS Obra Legal ®</title>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo $faviconUrl; ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <?php echo LocalConfig::getCustomCSS(); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Estilo para botões de ação agrupados */
        .btn-group .btn {
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            border-radius: 0;
        }
        
        .btn-group .btn:first-child {
            border-top-left-radius: 0.375rem;
            border-bottom-left-radius: 0.375rem;
        }
        
        .btn-group .btn:last-child {
            border-top-right-radius: 0.375rem;
            border-bottom-right-radius: 0.375rem;
        }
        
        .btn-group .btn-group .btn:first-child {
            border-radius: 0;
        }
        
        .btn-group .btn-group .btn:last-child {
            border-radius: 0;
        }
        
        /* Melhorar visibilidade dos tooltips */
        .btn[title]:hover::after {
            content: attr(title);
            position: absolute;
            z-index: 1000;
            background: #333;
            color: white;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            top: -35px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        /* Melhorar contraste dos ícones */
        .btn-outline-primary:hover i,
        .btn-outline-info:hover i,
        .btn-outline-warning:hover i,
        .btn-outline-success:hover i,
        .btn-outline-secondary:hover i,
        .btn-outline-danger:hover i {
            color: white;
        }
        
        /* Padronizar largura da coluna Ações em todas as tabelas */
        .table th:last-child,
        .table td:last-child {
            width: 180px;
            min-width: 180px;
        }
        
        /* Compactar colunas de data em todas as tabelas */
        #planejamentos .table th:nth-child(6),
        #planejamentos .table td:nth-child(6),
        #simulacoes .table th:nth-child(8),
        #simulacoes .table td:nth-child(8) {
            width: 100px;
            min-width: 100px;
            white-space: nowrap;
        }

        /* Estilos para toast de notificação */
        .toast-notification-admin {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .toast-notification-admin.success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .toast-notification-admin.error {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
        }

        .toast-notification-admin.show {
            opacity: 1;
            transform: translateX(0);
        }

        /* Garantir comportamento sanfona para sidebar-collapsed - forçar especificidade */
        #wrapper.sidebar-collapsed #sidebar-wrapper {
            width: 60px !important;
            transition: width 0.3s ease-in-out !important;
        }

        #wrapper.sidebar-collapsed #page-content-wrapper {
            margin-left: 60px !important;
            width: calc(100% - 60px) !important;
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out !important;
        }

        /* Estados iniciais para garantir transição suave */
        #sidebar-wrapper {
            width: 240px !important;
            transition: width 0.3s ease-in-out !important;
        }

        #page-content-wrapper {
            margin-left: 240px !important;
            width: calc(100% - 240px) !important;
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out !important;
        }
    </style>
</head>
<?php
$sidebarPosition = $config['theme']['layout']['sidebar_position'] ?? 'left';
?>
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-content-wrapper">
                <div class="sidebar-heading text-center py-4" style="background: none;">
                    <?php if (!empty($logoFile)): ?>
                        <img class="sidebar-logo" src="<?php echo $logoUrl; ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <span class="text-white fw-bold fs-5"><?php echo $config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'; ?></span>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush">
                    <a href="javascript:void(0);" onclick="window.history.back();" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-arrow-left fa-fw me-2"></i><span class="sidebar-text">Voltar</span></a>
                    <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-tachometer-alt fa-fw me-2"></i><span class="sidebar-text">Dashboard</span></a>
                    <a href="admin.php" class="list-group-item list-group-item-action bg-dark text-white active"><i class="fas fa-user-shield fa-fw me-2"></i><span class="sidebar-text">Admin</span></a>
                    <a href="#usuarios" class="list-group-item list-group-item-action bg-dark text-white" onclick="scrollToSection('usuarios')"><i class="fas fa-users fa-fw me-2"></i><span class="sidebar-text">Usuários</span></a>
                    <a href="#simulacoes" class="list-group-item list-group-item-action bg-dark text-white" onclick="scrollToSection('simulacoes')"><i class="fas fa-list fa-fw me-2"></i><span class="sidebar-text">Simulações</span></a>
                    <a href="admin_vau.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-table fa-fw me-2"></i><span class="sidebar-text">Tabela VAU</span></a>
                    <a href="admin_concreto_usinado.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-cubes fa-fw me-2"></i><span class="sidebar-text">Concreto Usinado</span></a>
                    <a href="admin_selic.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-percentage fa-fw me-2"></i><span class="sidebar-text">Tabela SELIC</span></a>
                    <a href="admin_settings.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-cog fa-fw me-2"></i><span class="sidebar-text">Configurações</span></a>
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
                                <i class="fas fa-user-shield me-2"></i><?php echo htmlspecialchars($user['nome']); ?> (<?php echo ucfirst($user['user_type']); ?>)
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="dashboard.php">Meu Dashboard</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php">Sair</a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4">
                <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-shield-alt me-2"></i>Painel Administrativo</h1>

                <!-- Cards de Resumo -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Simulações (Global)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalSimulacoesGlobal; ?></div>
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
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total de Usuários Ativos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalUsuariosAtivos; ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Valor Total INSS (Global)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ <?php echo number_format($valorTotalINSS, 2, ',', '.'); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Novas Simulações (7 dias)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $novasSimulacoes7Dias; ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-calendar-check fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white"><h6 class="m-0 font-weight-bold"><i class="fas fa-calendar-check fa-2x text-gray-300"></i> Atividade Mensal (Últimos 6 Meses)</h6></div>
                            <div class="card-body"><canvas id="lineChart"></canvas></div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white"><h6 class="m-0 font-weight-bold">Top 5 UFs por Simulações</h6></div>
                            <div class="card-body"><canvas id="barChart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white"><h6 class="m-0 font-weight-bold">Distribuição por Área (m²)</h6></div>
                            <div class="card-body"><canvas id="pieAreaChart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white"><h6 class="m-0 font-weight-bold">Distribuição por Destinação</h6></div>
                            <div class="card-body"><canvas id="pieDestinacaoChart"></canvas></div>
                        </div>
                    </div>
                </div>

                <!-- Seções de Gerenciamento (Já existentes) -->
                <div id="usuarios" class="pt-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users-cog me-2"></i>Gerenciamento de Usuários</h6>
                        </div>
                        <div class="card-body">
                            <!-- Filtros de Usuários -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Nome</label>
                                    <input type="text" id="filtroUsuarioNome" class="form-control" placeholder="Buscar por nome...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Email</label>
                                    <input type="text" id="filtroUsuarioEmail" class="form-control" placeholder="Buscar por email...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Categoria</label>
                                    <select id="filtroUsuarioCategoria" class="form-select">
                                        <option value="todas">Todas</option>
                                        <option value="lead">Lead</option>
                                        <option value="client">Cliente</option>
                                        <option value="admin">Admin</option>
                                        <?php if (Auth::isSuperAdmin()): ?>
                                            <option value="superadmin">SuperAdmin</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select id="filtroUsuarioStatus" class="form-select">
                                        <option value="todas">Todos</option>
                                        <option value="ativo">Ativo</option>
                                        <option value="inativo">Inativo</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="table-responsive" id="usuarios-tabela-container">
                                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Email</th>
                                            <th>Telefone</th>
                                            <th>Categoria</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Mostrar apenas os primeiros 10 usuários inicialmente
                                        $usuariosExibir = array_slice($usuarios, 0, 10);
                                        foreach ($usuariosExibir as $u): ?>
                                            <tr>
                                                <td><?php echo $u['id']; ?></td>
                                                <td><?php echo htmlspecialchars($u['nome']); ?></td>
                                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                                <td><?php echo htmlspecialchars($u['telefone'] ?? '-'); ?></td>
                                                <td>
                                                    <?php
                                                    $userType = $u['user_type'] ?? 'lead';
                                                    $badgeClass = '';
                                                    $displayName = '';
                                                    switch($userType) {
                                                        case 'superadmin':
                                                            $badgeClass = 'bg-danger';
                                                            $displayName = 'SuperAdmin';
                                                            break;
                                                        case 'admin':
                                                            $badgeClass = 'bg-warning';
                                                            $displayName = 'Admin';
                                                            break;
                                                        case 'lead':
                                                            $badgeClass = 'bg-primary';
                                                            $displayName = 'Lead';
                                                            break;
                                                        case 'client':
                                                            $badgeClass = 'bg-info';
                                                            $displayName = 'Cliente';
                                                            break;
                                                        default:
                                                            $badgeClass = 'bg-secondary';
                                                            $displayName = ucfirst($userType);
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo $displayName; ?></span>
                                                </td>
                                                <td><span class="badge bg-<?php echo $u['ativo'] ? 'success' : 'warning'; ?>"><?php echo $u['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                                                <td>
                                                    <?php if ($userType === 'superadmin' && !Auth::isSuperAdmin()): ?>
                                                        <!-- Admins não podem alterar SuperAdmins -->
                                                        <span class="badge bg-secondary small">Apenas SuperAdmin</span>
                                                    <?php else: ?>
                                                        <div class="btn-group" role="group">
                                                            <!-- Botão Editar -->
                                                            <button type="button" class="btn btn-outline-primary" title="Editar dados do usuário" 
                                                                    data-bs-toggle="modal" data-bs-target="#editUserModal" 
                                                                    data-user-id="<?php echo $u['id']; ?>"
                                                                    data-user-nome="<?php echo htmlspecialchars($u['nome']); ?>"
                                                                    data-user-email="<?php echo htmlspecialchars($u['email']); ?>"
                                                                    data-user-telefone="<?php echo htmlspecialchars($u['telefone'] ?? ''); ?>"
                                                                    data-user-type="<?php echo $userType; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            
                                                            <!-- Botão Alterar Senha -->
                                                            <button type="button" class="btn btn-outline-info" title="Alterar senha" 
                                                                    data-bs-toggle="modal" data-bs-target="#changePasswordModal" 
                                                                    data-user-id="<?php echo $u['id']; ?>">
                                                                <i class="fas fa-key"></i>
                                                            </button>
                                                            
                                                            <?php if ($u['id'] != $user['id']): // Não pode alterar a si mesmo ?>
                                                                <!-- Dropdown para Categoria -->
                                                                <div class="btn-group" role="group">
                                                                    <button type="button" class="btn btn-outline-warning dropdown-toggle" title="Alterar categoria" 
                                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                                        <i class="fas fa-user-tag"></i>
                                                                    </button>
                                                                    <ul class="dropdown-menu">
                                                                        <?php if ($userType !== 'admin' && Auth::isSuperAdmin()): ?>
                                                                            <li><a class="dropdown-item" href="#" onclick="alterarCategoria(<?php echo $u['id']; ?>, 'admin')">
                                                                                <i class="fas fa-arrow-up text-success me-2"></i>Promover para Admin</a></li>
                                                                        <?php endif; ?>
                                                                        <?php if ($userType !== 'lead'): ?>
                                                                            <li><a class="dropdown-item" href="#" onclick="alterarCategoria(<?php echo $u['id']; ?>, 'lead')">
                                                                                <i class="fas fa-user text-primary me-2"></i>Definir como Lead</a></li>
                                                                        <?php endif; ?>
                                                                        <?php if ($userType !== 'client'): ?>
                                                                            <li><a class="dropdown-item" href="#" onclick="alterarCategoria(<?php echo $u['id']; ?>, 'client')">
                                                                                <i class="fas fa-user-tie text-info me-2"></i>Definir como Cliente</a></li>
                                                                        <?php endif; ?>
                                                                        <?php if ($userType === 'superadmin' && Auth::isSuperAdmin()): ?>
                                                                            <li><hr class="dropdown-divider"></li>
                                                                            <li><a class="dropdown-item" href="#" onclick="alterarCategoria(<?php echo $u['id']; ?>, 'admin')">
                                                                                <i class="fas fa-arrow-down text-warning me-2"></i>Rebaixar para Admin</a></li>
                                                                            <li><a class="dropdown-item" href="#" onclick="alterarCategoria(<?php echo $u['id']; ?>, 'lead')">
                                                                                <i class="fas fa-arrow-down text-warning me-2"></i>Rebaixar para Lead</a></li>
                                                                        <?php endif; ?>
                                                                        <?php if ($userType !== 'superadmin' && Auth::isSuperAdmin() && $u['id'] != $user['id']): ?>
                                                                            <li><hr class="dropdown-divider"></li>
                                                                            <li><a class="dropdown-item" href="#" onclick="alterarCategoria(<?php echo $u['id']; ?>, 'superadmin')">
                                                                                <i class="fas fa-crown text-danger me-2"></i>Promover para SuperAdmin</a></li>
                                                                        <?php endif; ?>
                                                                    </ul>
                                                                </div>
                                                                
                                                                <!-- Botão Ativar/Desativar -->
                                                                <button type="button" class="btn btn-outline-<?php echo $u['ativo'] ? 'secondary' : 'success'; ?>" 
                                                                        title="<?php echo $u['ativo'] ? 'Desativar' : 'Ativar'; ?> usuário" 
                                                                        onclick="toggleStatus(<?php echo $u['id']; ?>, <?php echo $u['ativo']; ?>)">
                                                                    <i class="fas fa-<?php echo $u['ativo'] ? 'user-slash' : 'user-check'; ?>"></i>
                                                                </button>
                                                                
                                                                <!-- Botão Excluir -->
                                                                <button type="button" class="btn btn-outline-danger" title="Excluir usuário" 
                                                                        onclick="excluirUsuario(<?php echo $u['id']; ?>)">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary small">Você mesmo</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginação Usuários -->
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    <span class="text-muted">Mostrando <span id="usuarios-inicio">1</span>-<span id="usuarios-fim"><?= min(10, count($usuarios)) ?></span> de <span id="usuarios-total"><?= count($usuarios) ?></span> usuários</span>
                                </div>
                                <div class="btn-group">
                                    <button id="usuarios-anterior" class="btn btn-secondary btn-sm me-2" disabled>
                                        <i class="fas fa-chevron-left"></i> Anterior
                                    </button>
                                    <button id="usuarios-proximo" class="btn btn-secondary btn-sm" <?php if (count($usuarios) <= 10) echo 'disabled'; ?>>
                                        Próximos <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- --- Tabela de Simulações --- -->
                <div id="simulacoes" class="pt-4">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white"><h6 class="m-0 font-weight-bold"><i class="fas fa-list-alt me-2"></i>Todas as Simulações do Sistema</h6></div>
                        <div class="card-body">
                            <!-- Filtros da Tabela -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="filtroUsuario" class="form-label">Filtrar por Usuário:</label>
                                    <select id="filtroUsuario" class="form-select">
                                        <option value="">Todos os Usuários</option>
                                        <?php 
                                        $nomesUsuarios = array_unique(array_column($usuarios, 'nome'));
                                        sort($nomesUsuarios);
                                        foreach ($nomesUsuarios as $nome): ?>
                                            <option value="<?php echo htmlspecialchars($nome); ?>"><?php echo htmlspecialchars($nome); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="filtroNome" class="form-label">Filtrar por Nome da Simulação:</label>
                                    <input type="text" id="filtroNome" class="form-control" placeholder="Digite o nome...">
                                </div>
                                <div class="col-md-4">
                                    <label for="filtroData" class="form-label">Filtrar por Data:</label>
                                    <input type="date" id="filtroData" class="form-control">
                                </div>
                            </div>
                            <div id="simulacoes-tabela-container">
                                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Usuário</th>
                                                <th>Nome da Simulação</th>
                                                <th>Destinação</th>
                                                <th>Responsável</th>
                                                <th>Área (m²)</th>
                                                <th>Data</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tabelaSimulacoesBody">
                                            <?php
                                            $simulacoesExibir = array_slice($todasSimulacoes, 0, 6);
                                            foreach ($simulacoesExibir as $sim): ?>
                                                <tr data-date-raw="<?php echo date('Y-m-d', strtotime($sim['criado_em'])); ?>">
                                                    <td><?php echo $sim['id']; ?></td>
                                                    <td>
                                                        <?php 
                                                            $autor = 'Usuário não encontrado';
                                                            foreach($usuarios as $usr) {
                                                                if ($usr['id'] == $sim['user_id']) {
                                                                    $autor = htmlspecialchars($usr['nome']);
                                                                    break;
                                                                }
                                                            }
                                                            echo $autor;
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($sim['nome']) ?></strong>
                                                        <?php if ($sim['has_planejamento']): ?>
                                                            <i class="fas fa-file-alt text-info ms-2" 
                                                               title="Esta simulação possui um Planejamento Tributário associado."></i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($sim['destinacao'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($sim['responsavel'] ?? 'Pessoa Física'); ?></td>
                                                    <td><?php echo number_format($sim['area'], 2, ',', '.'); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($sim['criado_em'])); ?></td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="relatorio_simulacao.php?id=<?php echo $sim['id']; ?>" class="btn btn-outline-primary" title="Visualizar"><i class="fas fa-eye"></i></a>
                                                            <button type="button" class="btn btn-outline-info" onclick="window.location.href='duplicar_simulacao.php?id=<?php echo $sim['id']; ?>'" title="Duplicar"><i class="fas fa-copy"></i></button>
                                                            <?php if ($sim['user_id'] == $user['id']): ?>
                                                                <a href="editar_simulacao.php?id=<?php echo $sim['id']; ?>" class="btn btn-outline-warning" title="Editar"><i class="fas fa-edit"></i></a>
                                                            <?php else: ?>
                                                                <button class="btn btn-outline-warning" disabled title="Apenas o criador pode editar"><i class="fas fa-edit"></i></button>
                                                            <?php endif; ?>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="excluirSimulacao(<?php echo $sim['id']; ?>, <?php echo $sim['has_planejamento'] ? 'true' : 'false'; ?>, this)" 
                                                                    title="Excluir">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end mt-2">
                                    <button id="simulacoes-anterior" class="btn btn-secondary btn-sm me-2" disabled><i class="fas fa-chevron-left"></i> Anterior</button>
                                    <button id="simulacoes-proximo" class="btn btn-secondary btn-sm" <?php if (count($todasSimulacoes) <= 6) echo 'disabled'; ?> >Próximos <i class="fas fa-chevron-right"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Planejamentos Tributários -->
                <div id="planejamentos" class="pt-4">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-info text-white"><h6 class="m-0 font-weight-bold"><i class="fas fa-balance-scale me-2"></i>Planejamentos Tributários Existentes</h6></div>
                        <div class="card-body">
                            <!-- Filtros da Tabela de Planejamentos -->
                            <form class="row mb-3 g-3 align-items-end" method="get" id="filtrosPlanejamentosForm">
                                <div class="col-md-3">
                                    <label for="filtroUsuarioPlanejamento" class="form-label">Filtrar por Usuário:</label>
                                    <input type="text" id="filtroUsuarioPlanejamento" name="usuario" class="form-control" placeholder="Digite o nome do usuário..." value="<?php echo htmlspecialchars($_GET['usuario'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="filtroNomeSimulacao" class="form-label">Filtrar por Nome da Simulação:</label>
                                    <input type="text" id="filtroNomeSimulacao" name="nome" class="form-control" placeholder="Digite o nome da simulação..." value="<?php echo htmlspecialchars($_GET['nome'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="filtroDestinacao" class="form-label">Filtrar por Destinação:</label>
                                    <input type="text" id="filtroDestinacao" name="destinacao" class="form-control" placeholder="Digite a destinação..." value="<?php echo htmlspecialchars($_GET['destinacao'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="filtroDataPlanejamento" class="form-label">Filtrar por Data:</label>
                                    <input type="date" id="filtroDataPlanejamento" name="data" class="form-control" value="<?php echo htmlspecialchars($_GET['data'] ?? ''); ?>">
                                </div>
                                <div class="col-md-12 text-end mt-2">
                                    <button type="submit" class="btn btn-info"><i class="fas fa-filter me-2"></i>Filtrar</button>
                                    <a href="admin.php#planejamentos" class="btn btn-secondary ms-2"><i class="fas fa-times me-2"></i>Limpar Filtros</a>
                                </div>
                            </form>
                            <div id="planejamentos-tabela-container">
                                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                    <table class="table table-bordered table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Usuário</th>
                                                <th>Nome da Simulação</th>
                                                <th>Destinação</th>
                                                <th>Período</th>
                                                <th>Criado em</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Função para formatar data no formato Mês/Ano
                                            function formatarDataPeriodo($data) {
                                                if (empty($data) || $data === '0000-00-00') return 'N/A';
                                                $meses = [
                                                    '01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', 
                                                    '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', 
                                                    '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'
                                                ];
                                                $timestamp = strtotime($data);
                                                $mes = date('m', $timestamp);
                                                $ano = date('Y', $timestamp);
                                                return $meses[$mes] . '/' . $ano;
                                            }

                                            require_once __DIR__ . '/../src/Models/PlanejamentoTributario.php';
                                            $filtrosPlanejamento = [
                                                'usuario' => $_GET['usuario'] ?? '',
                                                'nome' => $_GET['nome'] ?? '',
                                                'destinacao' => $_GET['destinacao'] ?? '',
                                                'data' => $_GET['data'] ?? ''
                                            ];
                                            $planejamentos = PlanejamentoTributario::allWithUserAndSimulacao($filtrosPlanejamento, 6, 0);
                                            if (empty($planejamentos)) {
                                                echo '<tr><td colspan="7" class="text-center text-muted">Nenhum planejamento encontrado.</td></tr>';
                                            } else {
                                                foreach ($planejamentos as $p): ?>
                                                    <tr>
                                                        <td><?php echo $p['id']; ?></td>
                                                        <td>
                                                            <?php echo htmlspecialchars($p['usuario_nome']); ?>
                                                            <?php if (!empty($p['created_by_admin'])): ?>
                                                                <i class="fas fa-user-cog text-info ms-2" title="Planejamento criado por um Administrador"></i>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($p['simulacao_nome']); ?></td>
                                                        <td><?php echo htmlspecialchars($p['destinacao']); ?></td>
                                                        <td><?php echo formatarDataPeriodo($p['periodo_inicio']) . ' a ' . formatarDataPeriodo($p['periodo_fim']); ?></td>
                                                        <td><?php echo date('d/m/Y', strtotime($p['criado_em'])); ?></td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="planejamento_tributario.php?simulacao_id=<?php echo urlencode($p['simulacao_id']); ?>" 
                                                                   class="btn btn-outline-warning" title="Editar Planejamento">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="uploads/planejamentos/<?php echo preg_replace('/[^a-z0-9_\-]/i', '_', strtolower($p['simulacao_nome'])) . '-' . $p['simulacao_id']; ?>.pdf" 
                                                                   class="btn btn-outline-success" target="_blank" title="Baixar PDF">
                                                                    <i class="fas fa-file-pdf"></i>
                                                                </a>
                                                                <button type="button" class="btn btn-outline-danger" 
                                                                        onclick="submeterExclusaoPlanejamento(<?php echo $p['id']; ?>, this)" title="Excluir Planejamento">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach;
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end mt-2">
                                    <button id="planejamentos-anterior" class="btn btn-secondary btn-sm me-2" disabled><i class="fas fa-chevron-left"></i> Anterior</button>
                                    <button id="planejamentos-proximo" class="btn btn-secondary btn-sm" <?php if (count($planejamentos) < 6) echo 'disabled'; ?> >Próximos <i class="fas fa-chevron-right"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <?php include 'footer_simple.php'; ?>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Função de scroll suave
    window.scrollToSection = function(id) {
        const element = document.getElementById(id);
        if(element) {
            element.scrollIntoView({ behavior: 'smooth' });
        }
    }

    // Lógica de Filtro para a tabela de simulações
    const filtroUsuario = document.getElementById('filtroUsuario');
    const filtroNome = document.getElementById('filtroNome');
    const filtroData = document.getElementById('filtroData');
    const tabelaBody = document.getElementById('tabelaSimulacoesBody');
    if (tabelaBody) {
        const linhas = tabelaBody.getElementsByTagName('tr');

        function aplicarFiltros() {
            const usuarioFiltro = filtroUsuario.value.toLowerCase();
            const nomeFiltro = filtroNome.value.toLowerCase();
            const dataFiltro = filtroData.value;

            for (const linha of linhas) {
                const colunaUsuario = linha.cells[1].textContent.toLowerCase().trim();
                const colunaNome = linha.cells[2].textContent.toLowerCase().trim();
                const linhaData = linha.getAttribute('data-date-raw');

                const matchUsuario = usuarioFiltro === '' || colunaUsuario === usuarioFiltro;
                const matchNome = nomeFiltro === '' || colunaNome.includes(nomeFiltro);
                const matchData = dataFiltro === '' || linhaData === dataFiltro;

                if (matchUsuario && matchNome && matchData) {
                    linha.style.display = '';
                } else {
                    linha.style.display = 'none';
                }
            }
        }
        if(filtroUsuario) filtroUsuario.addEventListener('change', aplicarFiltros);
        if(filtroNome) filtroNome.addEventListener('input', aplicarFiltros);
        if(filtroData) filtroData.addEventListener('change', aplicarFiltros);
    }

    // Gráfico de Linha
    const lineChartEl = document.getElementById('lineChart');
    if (lineChartEl) {
        new Chart(lineChartEl, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($mesesLabels); ?>,
                datasets: [{
                    label: 'Novas Simulações',
                    data: <?php echo json_encode($novasSimulacoesPorMes); ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.3
                }, {
                    label: 'Novos Usuários',
                    data: <?php echo json_encode($novosUsuariosPorMes); ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // Gráfico de Barras
    const barChartEl = document.getElementById('barChart');
    if(barChartEl) {
        new Chart(barChartEl, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($ufsLabels); ?>,
                datasets: [{
                    label: 'Nº de Simulações',
                    data: <?php echo json_encode($ufsData); ?>,
                    backgroundColor: '#0d6efd'
                }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false }
        });
    }

    // Gráfico de Pizza (Área)
    const pieAreaChartEl = document.getElementById('pieAreaChart');
    if(pieAreaChartEl) {
        new Chart(pieAreaChartEl, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($faixasAreaLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($faixasAreaData); ?>,
                    backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#fd7e14', '#dc3545']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // Gráfico de Pizza (Destinação)
    const pieDestinacaoChartEl = document.getElementById('pieDestinacaoChart');
    if(pieDestinacaoChartEl) {
        new Chart(pieDestinacaoChartEl, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($destinacoesLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($destinacoesData); ?>,
                     backgroundColor: ['#0d6efd', '#6c757d', '#198754', '#ffc107', '#0dcaf0', '#dc3545', '#fd7e14', '#212529']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
    
    // Configurar máscara do telefone no modal de edição
    var editTelefoneField = document.getElementById('editUserTelefone');
    if (editTelefoneField) {
        editTelefoneField.addEventListener('input', function(e) {
            aplicarMascaraTelefone(e.target);
        });
    }
});

// Função para formatar telefone para exibição (números -> (85) 99999-9999)
function formatarTelefoneParaExibicao(telefone) {
    if (!telefone) return '';
    
    // Remove todos os caracteres não numéricos
    var numeros = telefone.replace(/\D/g, '');
    
    // Aplica formatação baseada no tamanho
    if (numeros.length <= 2) {
        return numeros.replace(/(\d{0,2})/, '($1');
    } else if (numeros.length <= 7) {
        return numeros.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    } else if (numeros.length <= 11) {
        return numeros.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    }
    
    // Limita a 11 dígitos
    return numeros.substring(0, 11).replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
}

// Função para aplicar máscara de telefone em tempo real
function aplicarMascaraTelefone(campo) {
    var value = campo.value.replace(/\D/g, '');
    
    if (value.length <= 11) {
        if (value.length <= 2) {
            value = value.replace(/(\d{0,2})/, '($1');
        } else if (value.length <= 7) {
            value = value.replace(/(\d{2})(\d{0,5})/, '($1) $2');
        } else {
            value = value.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
        }
    }
    
    campo.value = value;
}

// Função para remover máscara do telefone (para envio)
function removerMascaraTelefone(telefone) {
    return telefone ? telefone.replace(/\D/g, '') : '';
}

// Funções para gerenciamento de usuários
function alterarCategoria(id, novaCategoria) {
    const categorias = {
        'admin': 'Administrador',
        'lead': 'Lead',
        'client': 'Cliente',
        'superadmin': 'SuperAdmin'
    };
    
    let confirmMsg = `Tem certeza que deseja alterar a categoria deste usuário para ${categorias[novaCategoria]}?`;
    
    // Mensagens específicas para rebaixamento
    if (novaCategoria === 'admin') {
        confirmMsg = 'Tem certeza que deseja promover este usuário para Administrador?';
    } else if (novaCategoria === 'lead') {
        confirmMsg = 'Tem certeza que deseja definir este usuário como Lead?';
    } else if (novaCategoria === 'client') {
        confirmMsg = 'Tem certeza que deseja definir este usuário como Cliente?';
    } else if (novaCategoria === 'superadmin') {
        confirmMsg = 'Tem certeza que deseja promover este usuário para SuperAdmin?';
    }
    
    if (!confirm(confirmMsg)) return;
    
    const requestData = {
        action: 'alterar_categoria',
        id: id,
        categoria: novaCategoria
    };
    
    console.log('Enviando requisição:', requestData);
    
    fetch('admin_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            alert(data.message);
            // Scroll suave para a seção de usuários
            const usuariosSection = document.getElementById('usuarios');
            if (usuariosSection) {
                usuariosSection.scrollIntoView({ behavior: 'smooth' });
            }
            // Atualizar a página para refletir a alteração
            setTimeout(() => { location.hash = '#usuarios'; location.reload(); }, 500);
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar a requisição');
    });
}

function promoverUsuario(id, tipoAtual) {
    const action = tipoAtual === 'admin' ? 'rebaixar_usuario' : 'promover_usuario';
    const confirmMsg = tipoAtual === 'admin' ? 'Tem certeza que deseja rebaixar este usuário?' : 'Tem certeza que deseja promover este usuário a administrador?';
    
    if (!confirm(confirmMsg)) return;
    
    fetch('admin_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: action,
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar a requisição');
    });
}

function toggleStatus(id, statusAtual) {
    const confirmMsg = statusAtual ? 'Tem certeza que deseja desativar este usuário?' : 'Tem certeza que deseja ativar este usuário?';
    
    if (!confirm(confirmMsg)) return;
    
    fetch('admin_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'toggle_usuario',
            id: id,
            status: !statusAtual
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar a requisição');
    });
}

function excluirUsuario(id) {
    if (!confirm('Tem certeza que deseja excluir este usuário? Todas as simulações e planejamentos tributários associados a ele serão permanentemente removidos. Esta ação não pode ser desfeita.')) return;
    
    fetch('admin_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'excluir_usuario',
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar a requisição');
    });
}

function excluirSimulacao(id, hasPlanejamento, element) {
    let mensagem = 'Tem certeza que deseja excluir esta simulação? Esta ação não pode ser desfeita.';
    if (hasPlanejamento) {
        mensagem = 'Esta simulação possui um Planejamento Tributário associado. Ao excluí-la, o planejamento também será removido. Tem certeza que deseja continuar? Esta ação não pode ser desfeita.';
    }

    if (!confirm(mensagem)) return;

    fetch('admin_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'excluir_simulacao', id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove a linha com efeito visual suave
            const row = element.closest('tr');
            if (row) {
                row.style.transition = 'opacity 0.5s ease';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    // Recarregar a tabela de simulações via AJAX para atualizar numeração e paginação
                    if (typeof carregarSimulacoesAdmin === 'function') {
                        carregarSimulacoesAdmin(0); // Recarregar do início
                    }
                    // Se tinha planejamento, também recarregar a tabela de planejamentos
                    if (hasPlanejamento && typeof window.carregarPlanejamentosAdmin === 'function') {
                        window.planejamentosOffset = 0;
                        window.carregarPlanejamentosAdmin(0);
                    }
                }, 500);
            }
            showToastAdmin('Simulação excluída com sucesso!', 'success');
        } else {
            showToastAdmin('Erro ao excluir: ' + (data.message || 'Erro desconhecido'), 'error');
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        showToastAdmin('Ocorreu um erro de comunicação.', 'error');
    });
}

function excluirPlanejamentoAdmin(id) {
    if (!confirm('Tem certeza que deseja excluir este planejamento?')) return;
    fetch('excluir_planejamento_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(resp => {
        // Recarregar tabela
        if (typeof carregarPlanejamentosAdmin === 'function') {
            planejamentosOffset = 0;
            carregarPlanejamentosAdmin(0);
        }
    });
}

// Aguardar o DOM carregar
document.addEventListener('DOMContentLoaded', function() {
    // Passar o ID do usuário para o modal de alteração de senha
    var changePasswordModal = document.getElementById('changePasswordModal');
    if (changePasswordModal) {
        changePasswordModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var userId = button.getAttribute('data-user-id');
            var modalUserIdInput = changePasswordModal.querySelector('#modalUserId');
            modalUserIdInput.value = userId;
        });
    }

    // Gerenciar modal de edição de usuário
    var editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        editUserModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var userId = button.getAttribute('data-user-id');
            var userName = button.getAttribute('data-user-nome');
            var userEmail = button.getAttribute('data-user-email');
            var userTelefone = button.getAttribute('data-user-telefone');
            var userType = button.getAttribute('data-user-type');
            
            // Preencher campos do modal
            editUserModal.querySelector('#editUserId').value = userId;
            editUserModal.querySelector('#editUserNome').value = userName;
            editUserModal.querySelector('#editUserEmail').value = userEmail;
            editUserModal.querySelector('#editUserTelefone').value = userTelefone;
            editUserModal.querySelector('#editUserType').value = userType;
            
            // Mostrar campo de categoria apenas para SuperAdmin
            var isSuperAdmin = <?php echo Auth::isSuperAdmin() ? 'true' : 'false'; ?>;
            var userTypeGroup = editUserModal.querySelector('#editUserTypeGroup');
            if (isSuperAdmin) {
                userTypeGroup.style.display = 'block';
            } else {
                userTypeGroup.style.display = 'none';
            }
            
            // Aplicar máscara ao telefone se tiver valor
            var telefoneField = editUserModal.querySelector('#editUserTelefone');
            if (userTelefone && telefoneField) {
                // Formatar telefone para exibição (apenas números vêm do banco)
                telefoneField.value = formatarTelefoneParaExibicao(userTelefone);
            }
        });
    }

    // Tratar envio do formulário de alteração de senha
    var changePasswordForm = document.getElementById('changePasswordForm');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const requestData = {
                action: 'change_password',
                user_id: formData.get('user_id'),
                new_password: formData.get('new_password')
            };
            
            console.log('Enviando alteração de senha:', requestData);
            
            fetch('admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    alert(data.message);
                    // Fechar o modal
                    const modal = bootstrap.Modal.getInstance(changePasswordModal);
                    modal.hide();
                    // Limpar o formulário
                    this.reset();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao processar a requisição');
            });
        });
    } else {
        console.error('Formulário de alteração de senha não encontrado!');
    }

    // Tratar envio do formulário de edição de usuário
    var editUserForm = document.getElementById('editUserForm');
    if (editUserForm) {
        editUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const telefoneValue = removerMascaraTelefone(formData.get('telefone'));
            
            // Validar telefone se fornecido
            if (telefoneValue && (telefoneValue.length < 10 || telefoneValue.length > 11)) {
                alert('Por favor, digite um telefone válido com DDD (10 ou 11 dígitos).');
                return false;
            }
            
            const requestData = {
                action: 'edit_user',
                user_id: formData.get('user_id'),
                nome: formData.get('nome'),
                email: formData.get('email'),
                telefone: telefoneValue
            };
            
            // Incluir user_type apenas se SuperAdmin
            var isSuperAdmin = <?php echo Auth::isSuperAdmin() ? 'true' : 'false'; ?>;
            if (isSuperAdmin) {
                requestData.user_type = formData.get('user_type');
            }
            
            console.log('Enviando edição de usuário:', requestData);
            
            fetch('admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    alert(data.message);
                    // Fechar o modal
                    const modal = bootstrap.Modal.getInstance(editUserModal);
                    modal.hide();
                    // Recarregar a página para mostrar as alterações
                    setTimeout(() => { 
                        location.hash = '#usuarios'; 
                        location.reload(); 
                    }, 500);
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao processar a requisição');
            });
        });
    } else {
        console.error('Formulário de edição de usuário não encontrado!');
    }
});

function submeterExclusaoPlanejamento(id, element) {
    if (!confirm('Tem certeza que deseja excluir este planejamento? Esta ação não pode ser desfeita.')) return;

    fetch('excluir_planejamento_admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove a linha com efeito visual suave
            const row = element.closest('tr');
            if (row) {
                row.style.transition = 'opacity 0.5s ease';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    // Recarregar a tabela de planejamentos via AJAX para atualizar numeração e paginação
                    if (typeof carregarPlanejamentosAdmin === 'function') {
                        carregarPlanejamentosAdmin(0); // Recarregar do início
                    }
                }, 500);
            }
            showToastAdmin('Planejamento excluído com sucesso!', 'success');
        } else {
            showToastAdmin('Erro ao excluir: ' + (data.message || 'Erro desconhecido'), 'error');
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        showToastAdmin('Ocorreu um erro de comunicação.', 'error');
    });
}

function showToastAdmin(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification-admin ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }, 100);
}
</script>

<!-- Modal de Edição de Usuário -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editUserModalLabel">Editar Dados do Usuário</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editUserForm">
        <div class="modal-body">
          <input type="hidden" name="user_id" id="editUserId">
          
          <div class="mb-3">
            <label for="editUserNome" class="form-label">Nome <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="editUserNome" name="nome" required>
          </div>
          
          <div class="mb-3">
            <label for="editUserEmail" class="form-label">E-mail <span class="text-danger">*</span></label>
            <input type="email" class="form-control" id="editUserEmail" name="email" required>
          </div>
          
          <div class="mb-3">
            <label for="editUserTelefone" class="form-label">Telefone</label>
            <input type="tel" class="form-control" id="editUserTelefone" name="telefone" 
                   placeholder="(85) 99999-9999">
          </div>
          
          <!-- Mostrar campo de categoria apenas para SuperAdmin -->
          <div class="mb-3" id="editUserTypeGroup" style="display: none;">
            <label for="editUserType" class="form-label">Categoria</label>
            <select class="form-select" id="editUserType" name="user_type">
              <option value="lead">Lead</option>
              <option value="client">Cliente</option>
              <option value="admin">Admin</option>
              <option value="superadmin">SuperAdmin</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>Salvar Alterações
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal de Alteração de Senha -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changePasswordModalLabel">Alterar Senha do Usuário</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="changePasswordForm">
        <div class="modal-body">
          <input type="hidden" name="user_id" id="modalUserId">
          <div class="mb-3">
            <label for="newPassword" class="form-label">Nova Senha</label>
            <input type="password" class="form-control" id="newPassword" name="new_password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Formulário oculto para exclusão de planejamento -->
<form id="form-excluir-planejamento" method="POST" action="excluir_planejamento_admin.php" style="display:none;">
    <input type="hidden" name="id" id="input-excluir-planejamento-id" value="">
    <input type="hidden" name="redirect" value="admin.php#planejamentos">
</form>

<script src="assets/filtros.js"></script>

<script>
// Paginação e filtros de Usuários
document.addEventListener("DOMContentLoaded", function() {
    let usuariosOffset = 0;
    const usuariosLimit = 10;
    const usuariosTabelaContainer = document.getElementById('usuarios-tabela-container');
    const btnUsuariosProximo = document.getElementById('usuarios-proximo');
    const btnUsuariosAnterior = document.getElementById('usuarios-anterior');
    const filtroUsuarioNome = document.getElementById('filtroUsuarioNome');
    const filtroUsuarioEmail = document.getElementById('filtroUsuarioEmail');
    const filtroUsuarioCategoria = document.getElementById('filtroUsuarioCategoria');
    const filtroUsuarioStatus = document.getElementById('filtroUsuarioStatus');
    
    let filterTimer;
    
    function carregarUsuariosAdmin(offset) {
        const nome = filtroUsuarioNome?.value || '';
        const email = filtroUsuarioEmail?.value || '';
        const categoria = filtroUsuarioCategoria?.value || 'todas';
        const status = filtroUsuarioStatus?.value || 'todas';
        
        const url = `listar_usuarios_admin.php?offset=${offset}&limit=${usuariosLimit}&nome=${encodeURIComponent(nome)}&email=${encodeURIComponent(email)}&categoria=${encodeURIComponent(categoria)}&status=${encodeURIComponent(status)}`;
        
        console.log('🔍 Buscando usuários com URL:', url);
        console.log('📋 Parâmetros:', {offset, limit: usuariosLimit, nome, email, categoria, status});
        
        fetch(url)
            .then(r => {
                console.log('📥 Resposta recebida, status:', r.status);
                return r.json();
            })
            .then(resp => {
                console.log('✅ Dados recebidos:', resp);
                const tbody = usuariosTabelaContainer.querySelector('tbody');
                if (tbody) {
                    tbody.innerHTML = resp.html;
                    
                    // Atualizar botões de paginação
                    const total = resp.total || 0;
                    btnUsuariosAnterior.disabled = offset === 0;
                    btnUsuariosProximo.disabled = (offset + usuariosLimit) >= total;
                    
                    // Atualizar contador
                    const inicio = total > 0 ? offset + 1 : 0;
                    const fim = Math.min(offset + usuariosLimit, total);
                    document.getElementById('usuarios-inicio').textContent = inicio;
                    document.getElementById('usuarios-fim').textContent = fim;
                    document.getElementById('usuarios-total').textContent = total;
                    
                    // Reaplicar eventos dos modais aos novos botões
                    reaplicarEventosModais();
                }
            })
            .catch(error => {
                console.error('❌ Erro ao carregar usuários:', error);
                alert('Erro ao carregar usuários. Veja o console para mais detalhes.');
            });
    }
    
    function aplicarFiltrosUsuarios() {
        usuariosOffset = 0;
        carregarUsuariosAdmin(usuariosOffset);
    }
    
    // Event listeners para filtros
    console.log('Configurando event listeners para filtros de usuários...');
    console.log('filtroUsuarioNome:', filtroUsuarioNome);
    console.log('filtroUsuarioEmail:', filtroUsuarioEmail);
    console.log('filtroUsuarioCategoria:', filtroUsuarioCategoria);
    console.log('filtroUsuarioStatus:', filtroUsuarioStatus);
    
    if (filtroUsuarioNome) {
        filtroUsuarioNome.addEventListener('input', () => {
            console.log('Filtro nome alterado:', filtroUsuarioNome.value);
            clearTimeout(filterTimer);
            filterTimer = setTimeout(aplicarFiltrosUsuarios, 500);
        });
    } else {
        console.error('❌ Elemento filtroUsuarioNome não encontrado!');
    }
    
    if (filtroUsuarioEmail) {
        filtroUsuarioEmail.addEventListener('input', () => {
            console.log('Filtro email alterado:', filtroUsuarioEmail.value);
            clearTimeout(filterTimer);
            filterTimer = setTimeout(aplicarFiltrosUsuarios, 500);
        });
    } else {
        console.error('❌ Elemento filtroUsuarioEmail não encontrado!');
    }
    
    if (filtroUsuarioCategoria) {
        filtroUsuarioCategoria.addEventListener('change', () => {
            console.log('Filtro categoria alterado:', filtroUsuarioCategoria.value);
            aplicarFiltrosUsuarios();
        });
    } else {
        console.error('❌ Elemento filtroUsuarioCategoria não encontrado!');
    }
    
    if (filtroUsuarioStatus) {
        filtroUsuarioStatus.addEventListener('change', () => {
            console.log('Filtro status alterado:', filtroUsuarioStatus.value);
            aplicarFiltrosUsuarios();
        });
    } else {
        console.error('❌ Elemento filtroUsuarioStatus não encontrado!');
    }
    
    // Event listeners para paginação
    if (btnUsuariosProximo && btnUsuariosAnterior) {
        btnUsuariosProximo.addEventListener('click', function() {
            usuariosOffset += usuariosLimit;
            carregarUsuariosAdmin(usuariosOffset);
        });
        
        btnUsuariosAnterior.addEventListener('click', function() {
            usuariosOffset = Math.max(0, usuariosOffset - usuariosLimit);
            carregarUsuariosAdmin(usuariosOffset);
        });
    }
    
    // Reaplicar eventos dos modais aos botões dinâmicos
    function reaplicarEventosModais() {
        // Botões de editar usuário
        const editButtons = document.querySelectorAll('[data-bs-target="#editUserModal"]');
        editButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const userNome = this.getAttribute('data-user-nome');
                const userEmail = this.getAttribute('data-user-email');
                const userTelefone = this.getAttribute('data-user-telefone');
                const userType = this.getAttribute('data-user-type');
                
                document.getElementById('editUserId').value = userId;
                document.getElementById('editUserNome').value = userNome;
                document.getElementById('editUserEmail').value = userEmail;
                document.getElementById('editUserTelefone').value = userTelefone;
                document.getElementById('editUserType').value = userType;
            });
        });
        
        // Botões de alterar senha
        const changePasswordButtons = document.querySelectorAll('[data-bs-target="#changePasswordModal"]');
        changePasswordButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                document.getElementById('modalUserId').value = userId;
            });
        });
    }
    
    // Chamar uma vez no carregamento inicial
    reaplicarEventosModais();
});
</script>

</body>
</html>

