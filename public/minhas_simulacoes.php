<?php
session_start();
require_once __DIR__ . '/../update_user_activity.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Controllers/SimulacaoController.php';
require_once __DIR__ . '/../src/Models/Simulacao.php';
require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';

if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

$user = Auth::user();

// Processar filtros GET
$filters = [
    'nome' => $_GET['nome'] ?? '',
    'destinacao' => $_GET['destinacao'] ?? '',
    'responsavel' => $_GET['responsavel'] ?? '',
    'uf' => $_GET['uf'] ?? ''
];

// Carregar simulações com filtros (limitado a 100 registros mais recentes)
$simulacoes = Simulacao::search($user['id'], $filters, 100);
$total_registros = Simulacao::count($user['id'], $filters);

// Estatísticas
$totalSimulacoes = count($simulacoes);
$totalINSS = array_sum(array_column($simulacoes, 'valor_inss'));
$destinacoes = [];
$tiposObra = [];
$responsaveis = [];
$ufs = [];

foreach ($simulacoes as $sim) {
    $destinacao = $sim['destinacao'];
    $tipo = $sim['tipo_obra'];
    $responsavel = $sim['responsavel'] ?? 'Pessoa Física';
    $uf = $sim['uf'];
    
    if (!isset($destinacoes[$destinacao])) {
        $destinacoes[$destinacao] = 0;
    }
    if (!isset($tiposObra[$tipo])) {
        $tiposObra[$tipo] = 0;
    }
    if (!isset($responsaveis[$responsavel])) {
        $responsaveis[$responsavel] = 0;
    }
    if (!isset($ufs[$uf])) {
        $ufs[$uf] = 0;
    }
    
    $destinacoes[$destinacao]++;
    $tiposObra[$tipo]++;
    $responsaveis[$responsavel]++;
    $ufs[$uf]++;
}

// Busca as destinações dinamicamente do banco de dados
$pdo = Database::getConnection();
$stmt_destinacoes = $pdo->query("SELECT DISTINCT destinacao FROM vau ORDER BY destinacao ASC");
$destinacoes_list = $stmt_destinacoes->fetchAll(PDO::FETCH_COLUMN);
rsort($destinacoes_list);

// Busca as UFs dinamicamente do banco de dados
$stmt_ufs = $pdo->query("SELECT DISTINCT uf FROM vau ORDER BY uf ASC");
$ufs_list = $stmt_ufs->fetchAll(PDO::FETCH_COLUMN);

$tipos_obra_list = [
    'Alvenaria',
    'Madeira',
    'Mista'
];

$responsaveis_list = [
    'Pessoa Física',
    'Pessoa Jurídica'
];

$config = LocalConfig::getAll();

// Cache-busting para logo e favicon
$baseUrl = LocalConfig::getBaseUrl();
$logoFile = $config['theme']['branding']['logo_file'] ?? '';
$logoFilePath = !empty($logoFile) ? __DIR__ . '/' . $logoFile : '';
$logoVersion = ($logoFilePath && file_exists($logoFilePath) ? filemtime($logoFilePath) : time());

$faviconFile = $config['theme']['branding']['favicon_file'] ?? '';
$faviconFilePath = !empty($faviconFile) ? __DIR__ . '/' . $faviconFile : '';
$faviconVersion = ($faviconFilePath && file_exists($faviconFilePath) ? filemtime($faviconFilePath) : time());
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Simulações - Calculadora INSS Obra Legal ®</title>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo LocalConfig::buildAssetUrl($faviconFile); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <?php echo LocalConfig::getCustomCSS(); ?>
    <style>
        /* Estilo para botões de ação agrupados - consistência com admin.php */
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
        
        /* Padronizar largura da coluna Ações */
        .table th:last-child,
        .table td:last-child {
            width: 180px;
            min-width: 180px;
        }
    </style>
</head>
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-content-wrapper">
                <div class="sidebar-heading">
                    <?php if (!empty($logoFile)): ?>
                        <img class="sidebar-logo" src="<?php echo LocalConfig::buildAssetUrl($logoFile); ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <span class="fw-bold"><?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush">
                    <a href="javascript:void(0);" onclick="window.history.back();" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-arrow-left me-2"></i><span class="sidebar-text">Voltar</span>
                    </a>
                    <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;">
                        <i class="fas fa-tachometer-alt me-2"></i><span class="sidebar-text">Dashboard</span>
                    </a>
                    <a href="nova_simulacao.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;">
                        <i class="fas fa-calculator me-2"></i><span class="sidebar-text">Nova Simulação</span>
                    </a>
                    <a href="minhas_simulacoes.php" class="list-group-item list-group-item-action bg-dark text-white active" onclick="window.location=this.href;return false;">
                        <i class="fas fa-list me-2"></i><span class="sidebar-text">Minhas Simulações</span>
                    </a>
                    <?php if (($user['user_type'] ?? 'lead') !== 'lead'): ?>
                    <a href="meus_planejamentos.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;">
                        <i class="fas fa-balance-scale me-2"></i><span class="sidebar-text">Meus Planejamentos</span>
                    </a>
                    <?php endif; ?>
                    <a href="relatorios.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;">
                        <i class="fas fa-chart-bar me-2"></i><span class="sidebar-text">Relatórios</span>
                    </a>
                    <a href="<?= Auth::isAdmin() ? 'admin.php' : 'perfil.php' ?>" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;">
                        <i class="fas fa-user me-2"></i><span class="sidebar-text">Meu Perfil</span>
                    </a>
                    <?php if (Auth::isAdmin()): ?>
                    <a href="admin.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;">
                        <i class="fas fa-user-shield me-2"></i><span class="sidebar-text">Admin</span>
                    </a>
                    <?php endif; ?>
                    <a href="logout.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;">
                        <i class="fas fa-sign-out-alt me-2"></i><span class="sidebar-text">Sair</span>
                    </a>
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
                                <i class="fas fa-user-circle me-2"></i><?= htmlspecialchars($user['nome']) ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="<?= Auth::isAdmin() ? 'admin.php' : 'perfil.php' ?>">Perfil</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php">Sair</a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Minhas Simulações</h1>
                    <div>
                        <a href="nova_simulacao.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Nova Simulação
                        </a>
                    </div>
                </div>

                <?php if (!empty($_SESSION['erro_exclusao_simulacao'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['erro_exclusao_simulacao']); unset($_SESSION['erro_exclusao_simulacao']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_SESSION['sucesso_exclusao_simulacao'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['sucesso_exclusao_simulacao']); unset($_SESSION['sucesso_exclusao_simulacao']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                    </div>
                <?php endif; ?>

                <!-- Cards de Resumo -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total de Simulações</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalSimulacoes ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calculator fa-2x text-gray-300"></i>
                                    </div>
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
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Tipos de Obra</div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $tipos_detalhados = [];
                                            foreach ($tipos_obra_list as $tipo) {
                                                $count = $tiposObra[$tipo] ?? 0;
                                                if ($count > 0) {
                                                    $tipos_detalhados[] = $tipo . ': ' . $count;
                                                }
                                            }
                                            echo !empty($tipos_detalhados) ? implode('<br>', $tipos_detalhados) : 'Nenhuma';
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-building fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Responsáveis</div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $responsaveis_detalhados = [];
                                            foreach ($responsaveis_list as $responsavel) {
                                                $count = $responsaveis[$responsavel] ?? 0;
                                                if ($count > 0) {
                                                    $responsaveis_detalhados[] = $responsavel . ': ' . $count;
                                                }
                                            }
                                            echo !empty($responsaveis_detalhados) ? implode('<br>', $responsaveis_detalhados) : 'Nenhum';
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-filter me-2"></i>Filtros
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="get" id="filtrosForm" action="minhas_simulacoes.php">
                            <!-- Primeira linha de filtros -->
                            <div class="row align-items-end mb-3">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Nome da Simulação</label>
                                    <input type="text" class="form-control" name="nome" id="filtro-nome"
                                           value="<?= htmlspecialchars($filters['nome']) ?>"
                                           placeholder="Buscar por nome...">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Destinação</label>
                                    <select class="form-select" name="destinacao" id="filtro-destinacao">
                                        <option value="">Todas</option>
                                        <?php foreach ($destinacoes_list as $destinacao): ?>
                                            <option value="<?= $destinacao ?>" <?= ($filters['destinacao'] == $destinacao) ? 'selected' : '' ?>><?= $destinacao ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Responsável</label>
                                    <select class="form-select" name="responsavel" id="filtro-responsavel">
                                        <option value="">Todos</option>
                                        <?php foreach ($responsaveis_list as $responsavel): ?>
                                            <option value="<?= $responsavel ?>" <?= ($filters['responsavel'] == $responsavel) ? 'selected' : '' ?>><?= $responsavel ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">UF</label>
                                    <select class="form-select" name="uf" id="filtro-uf">
                                        <option value="">Todas</option>
                                        <?php foreach ($ufs_list as $uf): ?>
                                            <option value="<?= $uf ?>" <?= ($filters['uf'] == $uf) ? 'selected' : '' ?>><?= $uf ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Segunda linha com botões -->
                            <div class="row">
                                <div class="col-md-6">
                                    <a href="minhas_simulacoes.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Limpar Filtros
                                    </a>
                                    <button type="button" id="btn-exportar" class="btn btn-success ms-2">
                                        <i class="fas fa-file-csv me-2"></i>Exportar CSV
                                    </button>
                                </div>
                                <div class="col-md-6 text-end">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Exibindo até 100 registros mais recentes. Use "Exportar CSV" para acessar todos os registros.
                                    </small>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Botões de Ação em Massa -->
                <div class="card shadow mb-4" id="acoes-massa" style="display: none;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted">
                                    <i class="fas fa-check-square me-2"></i>
                                    <span id="contador-selecionados">0</span> simulação(ões) selecionada(s)
                                </span>
                            </div>
                            <div>
                                <button type="button" id="btn-excluir-multiplas" class="btn btn-danger">
                                    <i class="fas fa-trash me-2"></i>Excluir Selecionadas
                                </button>
                                <button type="button" id="btn-desmarcar-todas" class="btn btn-secondary ms-2">
                                    <i class="fas fa-times me-2"></i>Desmarcar Todas
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Simulações -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-table me-2"></i>Lista de Simulações (<?= $totalSimulacoes ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($simulacoes)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Nenhuma simulação encontrada</h5>
                            <p class="text-muted">Crie sua primeira simulação para começar</p>
                            <a href="nova_simulacao.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Nova Simulação
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selecionar-todas" class="form-check-input">
                                        </th>
                                        <th>Nome</th>
                                        <th>Responsável</th>
                                        <th>Destinação</th>
                                        <th>UF</th>
                                        <th>Área (m²)</th>
                                        <th>INSS</th>
                                        <th>Criado em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($simulacoes as $simulacao): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input checkbox-simulacao" value="<?= $simulacao['id'] ?>">
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($simulacao['nome']) ?></strong>
                                            <?php if ($simulacao['has_planejamento']): ?>
                                                <i class="fas fa-file-alt text-info ms-2" 
                                                   title="Esta simulação possui um Planejamento Tributário associado."></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($simulacao['responsavel'] ?? 'Pessoa Física') ?></td>
                                        <td><?= htmlspecialchars($simulacao['destinacao']) ?></td>
                                        <td><?= htmlspecialchars($simulacao['uf']) ?></td>
                                        <td><?= number_format($simulacao['area'], 2, ',', '.') ?></td>
                                        <td><strong>R$ <?= number_format($simulacao['valor_inss'], 2, ',', '.') ?></strong></td>
                                        <td><?= date('d/m/Y', strtotime($simulacao['criado_em'])) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="relatorio_simulacao.php?id=<?= $simulacao['id'] ?>" 
                                                   class="btn btn-outline-primary" title="Ver Relatório">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="editar_simulacao.php?id=<?= $simulacao['id'] ?>" 
                                                   class="btn btn-outline-warning" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-info" 
                                                        onclick="duplicarSimulacao(<?= $simulacao['id'] ?>)" title="Duplicar">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="excluirSimulacao(<?= $simulacao['id'] ?>, <?= $simulacao['has_planejamento'] ? 'true' : 'false' ?>, this)" title="Excluir">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <?php include 'footer_simple.php'; ?>
        </div>
        </div>
        <!-- /#page-content-wrapper -->

    </div>
    <!-- /#wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		const filtroNome = document.getElementById('filtro-nome');
		const filtroDestinacao = document.getElementById('filtro-destinacao');
		const filtroResponsavel = document.getElementById('filtro-responsavel');
		const filtroUf = document.getElementById('filtro-uf');

		// Debounce para o filtro de nome
		let timeoutId;
		if (filtroNome) {
			filtroNome.addEventListener('input', function() {
				clearTimeout(timeoutId);
				timeoutId = setTimeout(function() {
					document.getElementById('filtrosForm').submit();
				}, 500);
			});
		}
		
		if (filtroDestinacao) {
			filtroDestinacao.addEventListener('change', function() {
				document.getElementById('filtrosForm').submit();
			});
		}
		if (filtroResponsavel) {
			filtroResponsavel.addEventListener('change', function() {
				document.getElementById('filtrosForm').submit();
			});
		}
		if (filtroUf) {
			filtroUf.addEventListener('change', function() {
				document.getElementById('filtrosForm').submit();
			});
		}

		// Botão de exportação CSV
		const btnExportar = document.getElementById('btn-exportar');
		if (btnExportar) {
			btnExportar.addEventListener('click', function() {
				// Coletar filtros atuais
				const filtros = {
					nome: filtroNome ? filtroNome.value : '',
					destinacao: filtroDestinacao ? filtroDestinacao.value : '',
					responsavel: filtroResponsavel ? filtroResponsavel.value : '',
					uf: filtroUf ? filtroUf.value : ''
				};

				// Criar URL para exportação
				const params = new URLSearchParams(filtros);
				const url = 'exportar_simulacoes_csv.php?' + params.toString();
				
				// Fazer download
				window.location.href = url;
			});
		}
	});

	// Função para duplicar simulação
	function duplicarSimulacao(id) {
		if (confirm('Deseja duplicar esta simulação?')) {
			window.location.href = 'duplicar_simulacao.php?id=' + id;
		}
	}

	function excluirSimulacao(id, hasPlanejamento, element) {
        let mensagem = 'Tem certeza que deseja excluir esta simulação? Esta ação não pode ser desfeita.';
        if (hasPlanejamento) {
            mensagem = 'Esta simulação possui um Planejamento Tributário associado. Ao excluí-la, o planejamento também será removido. Tem certeza que deseja continuar? Esta ação não pode ser desfeita.';
        }

        if (confirm(mensagem)) {
            fetch('excluir_simulacao.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const row = element.closest('tr');
                    row.style.transition = 'opacity 0.5s ease';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 500);
                    showToast('Simulação excluída com sucesso!', 'success');
                } else {
                    showToast('Erro ao excluir: ' + (data.message || 'Erro desconhecido'), 'error');
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                showToast('Ocorreu um erro de comunicação.', 'error');
            });
        }
    }

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
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

    const style = document.createElement('style');
    style.textContent = `
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #28a745;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 1050;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .toast-notification.error {
            background-color: #dc3545;
        }
        .toast-notification.show {
            opacity: 1;
        }
    `;
    document.head.appendChild(style);

	// Gerenciamento de seleção múltipla
	const selecionarTodas = document.getElementById('selecionar-todas');
	const checkboxesSimulacao = document.querySelectorAll('.checkbox-simulacao');
	const acoesMassa = document.getElementById('acoes-massa');
	const contadorSelecionados = document.getElementById('contador-selecionados');
	const btnExcluirMultiplas = document.getElementById('btn-excluir-multiplas');
	const btnDesmarcarTodas = document.getElementById('btn-desmarcar-todas');

	// Função para atualizar contador e visibilidade dos botões
	function atualizarSelecao() {
		const selecionadas = document.querySelectorAll('.checkbox-simulacao:checked');
		const total = checkboxesSimulacao.length;
		
		contadorSelecionados.textContent = selecionadas.length;
		
		if (selecionadas.length > 0) {
			acoesMassa.style.display = 'block';
		} else {
			acoesMassa.style.display = 'none';
		}
		
		// Atualizar estado do checkbox "selecionar todas"
		if (selecionadas.length === total) {
			selecionarTodas.checked = true;
			selecionarTodas.indeterminate = false;
		} else if (selecionadas.length > 0) {
			selecionarTodas.checked = false;
			selecionarTodas.indeterminate = true;
		} else {
			selecionarTodas.checked = false;
			selecionarTodas.indeterminate = false;
		}
	}

	// Event listener para checkbox "selecionar todas"
	if (selecionarTodas) {
		selecionarTodas.addEventListener('change', function() {
			checkboxesSimulacao.forEach(checkbox => {
				checkbox.checked = this.checked;
			});
			atualizarSelecao();
		});
	}

	// Event listeners para checkboxes individuais
	checkboxesSimulacao.forEach(checkbox => {
		checkbox.addEventListener('change', atualizarSelecao);
	});

	// Botão para desmarcar todas
	if (btnDesmarcarTodas) {
		btnDesmarcarTodas.addEventListener('click', function() {
			checkboxesSimulacao.forEach(checkbox => {
				checkbox.checked = false;
			});
			selecionarTodas.checked = false;
			selecionarTodas.indeterminate = false;
			atualizarSelecao();
		});
	}

	// Botão para excluir múltiplas
	if (btnExcluirMultiplas) {
		btnExcluirMultiplas.addEventListener('click', function() {
			const selecionadas = document.querySelectorAll('.checkbox-simulacao:checked');
			if (selecionadas.length === 0) {
				alert('Selecione pelo menos uma simulação para excluir.');
				return;
			}
			
			const ids = Array.from(selecionadas).map(checkbox => checkbox.value);
			const confirmacao = confirm(`Tem certeza que deseja excluir ${selecionadas.length} simulação(ões)? Esta ação não pode ser desfeita.`);
			
			if (confirmacao) {
				// Criar formulário temporário para enviar os IDs
				const form = document.createElement('form');
				form.method = 'POST';
				form.action = 'excluir_simulacao.php';
				
				const input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'ids';
				input.value = JSON.stringify(ids);
				
				form.appendChild(input);
				document.body.appendChild(form);
				form.submit();
			}
		});
	}

	// Menu Toggle
	const menuToggle = document.getElementById("menu-toggle");
	if(menuToggle) {
		menuToggle.addEventListener("click", function(e) {
			e.preventDefault();
			document.getElementById("wrapper").classList.toggle("toggled");
		});
	}
	</script>
</body>
</html>

