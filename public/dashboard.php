<?php
// Configurar timeout e tratamento de erros
set_time_limit(30); // Timeout de 30 segundos
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros na tela, apenas logar

session_start();

// Redireciona para o login se não estiver autenticado
require_once __DIR__ . '/../src/Utils/Auth.php';
if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

// Carregar update_user_activity com tratamento de erro
try {
    require_once __DIR__ . '/../update_user_activity.php';
} catch (Exception $e) {
    error_log("Dashboard: Erro ao carregar update_user_activity.php - " . $e->getMessage());
    // Continuar mesmo se houver erro
}

require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';
require_once __DIR__ . '/../src/Controllers/SimulacaoController.php';

$user = Auth::user();
if (!$user) {
    header('Location: login.php');
    exit;
}

$config = LocalConfig::getAll();

// Cache-busting para logo e favicon
$baseUrl = LocalConfig::getBaseUrl();
$faviconFile = $config['theme']['branding']['favicon_file'] ?? '';
$logoFile = $config['theme']['branding']['logo_file'] ?? '';

// Usar buildAssetUrl para assets
$logoUrl = LocalConfig::buildAssetUrl($logoFile);
$faviconUrl = LocalConfig::buildAssetUrl($faviconFile);

$cssFilePath = __DIR__ . '/assets/style.css';
$cssVersion = file_exists($cssFilePath) ? filemtime($cssFilePath) : time();

if (($user['user_type'] ?? 'lead') === 'lead') {
    header('Location: minhas_simulacoes.php');
    exit;
}

// Carregar simulações com tratamento de erro
try {
    $simulacoes = SimulacaoController::listarSimulacoes($user['id']);
    if (!is_array($simulacoes)) {
        $simulacoes = [];
    }
} catch (Exception $e) {
    error_log("Dashboard: Erro ao carregar simulações - " . $e->getMessage());
    $simulacoes = [];
}

// --- Lógica para os Cards de Resumo ---
$totalSimulacoes = 0;
$somaValoresINSS = 0;
$somaAreas = 0;
$contagemDestinacoes = [];

if (!empty($simulacoes)) {
    $totalSimulacoes = count($simulacoes);
    $somaValoresINSS = array_sum(array_column($simulacoes, 'valor_inss'));
    $somaAreas = array_sum(array_column($simulacoes, 'area'));
    $contagemDestinacoes = array_count_values(array_column($simulacoes, 'destinacao'));
}

$mediaINSS = ($totalSimulacoes > 0) ? $somaValoresINSS / $totalSimulacoes : 0;
$mediaArea = ($totalSimulacoes > 0) ? $somaAreas / $totalSimulacoes : 0;

$destinacaoMaisComum = 'N/A';
if (!empty($contagemDestinacoes)) {
    arsort($contagemDestinacoes);
    $destinacaoMaisComum = key($contagemDestinacoes);
}

// --- Lógica para o Gráfico de Linha (Simulações nos últimos 6 meses) ---
$simulacoesPorMes = array_fill(0, 6, 0);
$mesesLabels = [];
$hoje = new DateTime();

for ($i = 5; $i >= 0; $i--) {
    $dataRef = (clone $hoje)->modify("-$i month");
    $mesesLabels[] = $dataRef->format('M/y');
    
    foreach ($simulacoes as $sim) {
        $dataSimulacao = new DateTime($sim['criado_em']);
        if ($dataSimulacao->format('Y-m') == $dataRef->format('Y-m')) {
            $simulacoesPorMes[5 - $i]++;
        }
    }
}

// --- Lógica para o Gráfico de Pizza (Distribuição por Destinação) ---
$destinacoesLabels = !empty($contagemDestinacoes) ? array_keys($contagemDestinacoes) : [];
$destinacoesData = !empty($contagemDestinacoes) ? array_values($contagemDestinacoes) : [];

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
    <title>Dashboard - Calculadora INSS Obra Legal ®</title>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo $faviconUrl; ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <?php echo LocalConfig::getCustomCSS(); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-content-wrapper">
                <div class="sidebar-heading">
                    <?php if (!empty($logoFile)): ?>
                        <img class="sidebar-logo" src="<?php echo $logoUrl; ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <span class="fw-bold"><?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white active" onclick="window.location=this.href;return false;"><i class="fas fa-tachometer-alt fa-fw me-2"></i><span class="sidebar-text">Dashboard</span></a>
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Meu Dashboard</h1>
                </div>
                
                <!-- Cards de Resumo -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Minhas Simulações</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalSimulacoes; ?></div>
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
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Valor Médio do INSS</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ <?php echo number_format($mediaINSS, 2, ',', '.'); ?></div>
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
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Média de Área</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($mediaArea, 2, ',', '.'); ?> m²</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-ruler-combined fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Destinação Mais Comum</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($destinacaoMaisComum); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-building fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row">
                    <div class="col-lg-7 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-primary text-white py-3">
                                <h6 class="m-0 font-weight-bold text-white">Minhas Simulações (Últimos 6 Meses)</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                                    <canvas id="simulacoesMesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-primary text-white py-3">
                                <h6 class="m-0 font-weight-bold text-white">Distribuição por Destinação</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                                    <canvas id="destinacaoChart"></canvas>
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
    // Variáveis para os gráficos
    let lineChart, pieChart;

    // Gráfico de Linha: Simulações por Mês
    const ctxLine = document.getElementById('simulacoesMesChart').getContext('2d');
    lineChart = new Chart(ctxLine, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($mesesLabels); ?>,
            datasets: [{
                label: 'Nº de Simulações',
                data: <?php echo json_encode($simulacoesPorMes); ?>,
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 2,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: {
                            size: window.innerWidth < 768 ? 10 : 12
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        font: {
                            size: window.innerWidth < 768 ? 10 : 12
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: window.innerWidth < 768 ? 10 : 12
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Gráfico de Pizza: Destinações
    const ctxPie = document.getElementById('destinacaoChart').getContext('2d');
    pieChart = new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($destinacoesLabels); ?>,
            datasets: [{
                label: 'Distribuição',
                data: <?php echo json_encode($destinacoesData); ?>,
                backgroundColor: [
                    '#0d6efd', '#6c757d', '#198754', '#ffc107', '#0dcaf0', '#dc3545', '#fd7e14'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        font: {
                            size: window.innerWidth < 768 ? 10 : 12
                        },
                        padding: window.innerWidth < 768 ? 10 : 15
                    }
                }
            }
        }
    });
    
    // Função para ajustar tamanho das fontes dos gráficos baseado no tamanho da tela
    function adjustChartFonts() {
        const isMobile = window.innerWidth < 768;
        const fontSize = isMobile ? 10 : 12;
        const legendPadding = isMobile ? 10 : 15;
        
        // Ajustar gráfico de linha
        if (lineChart) {
            lineChart.options.plugins.legend.labels.font.size = fontSize;
            lineChart.options.scales.x.ticks.font.size = fontSize;
            lineChart.options.scales.y.ticks.font.size = fontSize;
            lineChart.update();
        }
        
        // Ajustar gráfico de pizza
        if (pieChart) {
            pieChart.options.plugins.legend.labels.font.size = fontSize;
            pieChart.options.plugins.legend.labels.padding = legendPadding;
            pieChart.update();
        }
    }
    
    // Listener para redimensionamento da janela
    window.addEventListener('resize', function() {
        // Debounce para evitar muitas atualizações
        clearTimeout(window.resizeTimeout);
        window.resizeTimeout = setTimeout(adjustChartFonts, 250);
    });
    
    // Ajustar fontes inicialmente
    adjustChartFonts();
});
</script>
<?php include __DIR__ . '/includes/comunicado_migracao.php'; ?>
</body>
</html>

