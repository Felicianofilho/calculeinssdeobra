<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Controllers/PlanejamentoTributarioController.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';
require_once __DIR__ . '/../src/Models/PlanejamentoTributario.php';
require_once __DIR__ . '/../src/Models/Simulacao.php'; // Added for Simulacao model

if (!Auth::check() || Auth::isLead()) {
    header('Location: login.php');
    exit;
}

$user = Auth::user();
$config = LocalConfig::getAll();
$baseUrl = LocalConfig::getBaseUrl();

// Filtros (exemplo: nome, período)
$filters = [
    'nome' => $_GET['nome'] ?? '',
    'periodo_inicio' => $_GET['periodo_inicio'] ?? '',
    'periodo_fim' => $_GET['periodo_fim'] ?? '',
];

// Buscar planejamentos do usuário (limitado a 100)
$planejamentos = PlanejamentoTributarioController::listarPlanejamentosUsuario($user['id'], $filters, 100);
$totalPlanejamentos = count($planejamentos);
$valorTotal = array_sum(array_column($planejamentos, 'valor_total'));
$ultimoPlanejamento = $planejamentos[0]['criado_em'] ?? null;

// Calcular economia total e destinações
$economiaTotal = 0;
$destinacoes = [];
foreach ($planejamentos as $p) {
    $valor_inss = isset($p['valor_inss']) ? $p['valor_inss'] : 0;
    if (!$valor_inss && isset($p['simulacao_id'])) {
        // Buscar valor_inss da simulação se não veio no array
        $sim = Simulacao::find($p['simulacao_id']);
        $valor_inss = $sim['valor_inss'] ?? 0;
    }
    $economia = $valor_inss - $p['valor_total'];
    if ($economia > 0) $economiaTotal += $economia;
    if (!empty($p['destinacao'])) {
        $destinacoes[$p['destinacao']] = true;
    }
}
$destinacoesList = implode(', ', array_keys($destinacoes));

function slugify($str) {
    $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str); // Remove acentos
    $str = strtolower($str);
    $str = preg_replace('/[^a-z0-9 ]/', '', $str); // Remove caracteres especiais, exceto espaço
    $str = preg_replace('/\s+/', '_', $str); // Substitui espaços por _
    $str = preg_replace('/_+/', '_', $str); // Evita múltiplos underlines
    $str = trim($str, '_');
    return $str;
}

?><!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Planejamentos - Calculadora INSS Obra Legal ®</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <?php echo LocalConfig::getCustomCSS(); ?>
</head>
<body>
<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div class="bg-dark" id="sidebar-wrapper">
        <div class="sidebar-content-wrapper">
            <div class="sidebar-heading text-center py-4">
                <?php if (!empty($config['theme']['branding']['logo_file'])): ?>
                    <img class="sidebar-logo" src="<?php echo LocalConfig::buildAssetUrl($config['theme']['branding']['logo_file']); ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                <?php else: ?>
                    <span class="text-white fw-bold fs-5"><?php echo $config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'; ?></span>
                <?php endif; ?>
            </div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-tachometer-alt fa-fw me-2"></i><span class="sidebar-text">Dashboard</span></a>
                <a href="nova_simulacao.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-plus fa-fw me-2"></i><span class="sidebar-text">Nova Simulação</span></a>
                <a href="minhas_simulacoes.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-list-alt fa-fw me-2"></i><span class="sidebar-text">Minhas Simulações</span></a>
                <a href="meus_planejamentos.php" class="list-group-item list-group-item-action bg-dark text-white active"><i class="fas fa-balance-scale fa-fw me-2"></i><span class="sidebar-text">Meus Planejamentos</span></a>
                <?php if (Auth::isAdmin()): ?>
                <a href="admin.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-user-shield fa-fw me-2"></i><span class="sidebar-text">Admin</span></a>
                <?php endif; ?>
                <a href="logout.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-sign-out-alt fa-fw me-2"></i><span class="sidebar-text">Sair</span></a>
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
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($user['nome']); ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="perfil.php">Meu Perfil</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php">Sair</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-balance-scale me-2"></i>Meus Planejamentos</h1>
            </div>

            <!-- Cards de Resumo -->
            <div class="row mb-4">
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total de Planejamentos</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalPlanejamentos; ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-balance-scale fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Economia Total</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">R$ <?php echo number_format($economiaTotal, 2, ',', '.'); ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-piggy-bank fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col me-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Destinações</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($destinacoesList ?: '-'); ?></div>
                                </div>
                                <div class="col-auto"><i class="fas fa-building fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <form class="row mb-4 g-3 align-items-end" method="get">
                <div class="col-md-6">
                    <label for="nome" class="form-label">Nome da Obra</label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($filters['nome']); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Filtrar</button>
                </div>
            </form>

            <!-- Lista de Planejamentos -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2"></i>Lista de Planejamentos</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Período</th>
                                    <th>Valor Total</th>
                                    <th>Criado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($planejamentos)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">Nenhum planejamento encontrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($planejamentos as $p): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($p['nome']); ?>
                                                <?php if (isset($p['created_by_admin']) && $p['created_by_admin'] == 1): ?>
                                                    <i class="fas fa-user-cog text-info ms-2" title="Planejamento criado por um Administrador"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($p['periodo']); ?></td>
                                            <td>R$ <?php echo number_format($p['valor_total'], 2, ',', '.'); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($p['criado_em'])); ?></td>
                                            <td>
                                                <a href="planejamento_tributario.php?simulacao_id=<?php echo urlencode($p['simulacao_id']); ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit me-1"></i>Alterar</a>
                                                <a href="uploads/planejamentos/<?php echo slugify($p['nome']) . '-' . $p['simulacao_id']; ?>.pdf" class="btn btn-sm btn-success" target="_blank"><i class="fas fa-file-pdf me-1"></i>PDF</a>
                                                <a href="#" onclick="submeterExclusaoPlanejamentoMeus(<?php echo $p['id']; ?>, this);return false;" class="btn btn-sm btn-danger"><i class="fas fa-trash me-1"></i>Excluir</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
function submeterExclusaoPlanejamentoMeus(id, element) {
    if (!confirm('Tem certeza que deseja excluir este planejamento?')) return;

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
            // Encontra a linha da tabela (tr) e a remove com um efeito suave
            const row = element.closest('tr');
            row.style.transition = 'opacity 0.5s ease';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 500);
            showToast('Planejamento excluído com sucesso!', 'success');
        } else {
            showToast('Erro ao excluir: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        showToast('Ocorreu um erro de comunicação.', 'error');
    });
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

// Adicionar o CSS para o toast no head
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

// Toggle da Sidebar
var sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
if (sidebarToggleBtn) {
    sidebarToggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('wrapper').classList.toggle('toggled');
    });
}
</script>
</body>
</html> 