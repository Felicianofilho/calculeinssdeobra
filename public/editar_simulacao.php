<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Controllers/SimulacaoController.php';
require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Utils/CalculoINSS.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';
require_once __DIR__ . '/../src/Utils/PerformanceControl.php';

if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

// Verificar se ID foi fornecido
if (!isset($_GET['id'])) {
    header('Location: minhas_simulacoes.php');
    exit;
}

$id = $_GET['id'];
$dados = SimulacaoController::verSimulacao($id);

// Determinar URL de retorno
$voltar = 'relatorio_simulacao.php?id=' . $id;
if (isset($_GET['voltar'])) {
    $voltar = $_GET['voltar'];
} elseif (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'editar_simulacao.php') === false) {
    $voltar = $_SERVER['HTTP_REFERER'];
}

if (!$dados || $dados['user_id'] != $_SESSION['user_id']) {
    header('Location: minhas_simulacoes.php');
    exit;
}

$user = Auth::user();
$erros = [];

$responsaveis = [
    'Pessoa Física',
    'Pessoa Jurídica'
];

$destinacoes = [
    'Casa Popular',
    'Comercial Salas/Lojas',
    'Conj. Hab. Popular',
    'Galpão Ind.',
    'Res. Multifamiliar',
    'Res. Unifamiliar',
    'Edifício de Garagens'
];
rsort($destinacoes);

$tipos_obra = [
    'Alvenaria',
    'Madeira',
    'Mista'
];

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

require_once __DIR__ . '/../src/Utils/ProcessLock.php';

// Processar submissão do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Aplicar delay de performance antes do cálculo
    PerformanceControl::applyDelay('simulation_calculation');
    
    if (!ProcessLock::acquireLock('inss_calculation')) {
        $erros[] = "O sistema está processando outro cálculo no momento. Por favor, tente novamente em alguns segundos.";
    } else {
        try {
            $acao_data = $_POST['acao_data'] ?? 'manter';
            $dados_atualizados = [
                'user_id' => $user['id'],
                'responsavel' => $_POST['responsavel'] ?? 'Pessoa Física',
                'nome' => $_POST['nome'] ?? '',
                'destinacao' => $_POST['destinacao'] ?? '',
                'tipo_obra' => $_POST['tipo_obra'] ?? '',
                'uf' => $_POST['uf'] ?? '',
                'area' => floatval($_POST['area'] ?? 0),
                'municipio' => $_POST['municipio'] ?? '',
                'endereco' => $_POST['endereco'] ?? '',
                'regime_tributario' => $_POST['regime_tributario'] ?? null
            ];

            // Validar dados
            $erros = CalculoINSS::validarDados($dados_atualizados);
            
            if (empty($erros)) {
                $pdo = Database::getConnection();
                // Definir data de referência para o cálculo do VAU
                if ($acao_data === 'atualizar') {
                    $dados_atualizados['criado_em'] = date('Y-m-d H:i:s');
                    $data_simulacao = $dados_atualizados['criado_em'];
                } else {
                    $dados_atualizados['criado_em'] = $dados['criado_em']; // garantir que será salvo
                    $data_simulacao = $dados['criado_em'];
                }
                // Calcular INSS com a data correta
                $dados_calculo = $dados_atualizados;
                $dados_calculo['data_simulacao'] = $data_simulacao;
                $resultado = CalculoINSS::calcular($dados_calculo);
                $valor_inss = $resultado['inss'];
                $stmt = $pdo->prepare("UPDATE simulacoes SET nome=?, destinacao=?, tipo_obra=?, uf=?, area=?, municipio=?, endereco=?, valor_inss=?, responsavel=?, regime_tributario=?, criado_em=? WHERE id=? AND user_id=?");
                $stmt->execute([
                    $dados_atualizados['nome'], $dados_atualizados['destinacao'], $dados_atualizados['tipo_obra'], 
                    $dados_atualizados['uf'], $dados_atualizados['area'], $dados_atualizados['municipio'], 
                    $dados_atualizados['endereco'], $valor_inss, $dados_atualizados['responsavel'], 
                    $dados_atualizados['regime_tributario'], $dados_atualizados['criado_em'], $id, $_SESSION['user_id']
                ]);

                ProcessLock::releaseLock('inss_calculation');
                header('Location: ' . $voltar);
                exit;
            }
        } catch (Exception $e) {
            ProcessLock::releaseLock('inss_calculation');
            $erros[] = "Ocorreu um erro inesperado ao salvar as alterações.";
            error_log("Erro ao editar simulação: " . $e->getMessage());
        }
        ProcessLock::releaseLock('inss_calculation');
    }
}

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
    <title>Editar Simulação - <?= htmlspecialchars($dados['nome']) ?></title>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo LocalConfig::buildAssetUrl($faviconFile); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <?php echo LocalConfig::getCustomCSS(); ?>
<style>
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
        <h4 class="mt-3">Calculando...</h4>
    </div>
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
                    <a href="#dados-obra" class="list-group-item list-group-item-action bg-dark text-white" onclick="scrollToSection('dados-obra')"><i class="fas fa-building fa-fw me-2"></i><span class="sidebar-text">Dados da Obra</span></a>
                    <a href="#localizacao" class="list-group-item list-group-item-action bg-dark text-white" onclick="scrollToSection('localizacao')"><i class="fas fa-map-marker-alt fa-fw me-2"></i><span class="sidebar-text">Localização</span></a>
                    <?php if (Auth::isAdmin()): ?>
                    <a href="admin.php" class="list-group-item list-group-item-action bg-dark text-white" onclick="window.location=this.href;return false;"><i class="fas fa-user-shield fa-fw me-2"></i><span class="sidebar-text">Admin</span></a>
                    <?php endif; ?>
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
                    <button class="btn btn-outline-light" id="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>

                    <div class="navbar-nav ms-auto">
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-user me-2"></i><?= htmlspecialchars($user['nome']) ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="dashboard.php">Dashboard</a>
                                <a class="dropdown-item" href="minhas_simulacoes.php">Minhas Simulações</a>
                                <a class="dropdown-item" href="<?= Auth::isAdmin() ? 'admin.php' : 'perfil.php' ?>">Perfil</a>
                                <?php if (Auth::isAdmin()): ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="admin.php">Área Admin</a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php">Sair</a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-edit me-2"></i>
                        Editar Simulação de INSS
                    </h1>
                    <div>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.history.back();">
                            <i class="fas fa-arrow-left me-2"></i>Voltar
                        </button>
                    </div>
                </div>

                <?php if (!empty($erros)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Erros encontrados:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($erros as $erro): ?>
                            <li><?= htmlspecialchars($erro) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="post" id="formEdicao">
                    <!-- Dados Básicos da Simulação -->
                    <div class="card shadow mb-4" id="dados-obra">
                        <div class="card-header bg-primary text-white py-3">
                            <h6 class="m-0 font-weight-bold text-white">
                                <i class="fas fa-info-circle me-2"></i>Dados Básicos da Simulação
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-tag me-1"></i>Nome da Simulação
                                    </label>
                                    <input type="text" class="form-control" name="nome" 
                                           value="<?= htmlspecialchars($dados['nome']) ?>" 
                                           placeholder="Ex: Residencial Vila Nova - Fase 1" required>
                                    <div class="form-text">Escolha um nome descritivo para identificar esta simulação</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-user me-1"></i>Responsável
                                    </label>
                                    <select class="form-select" name="responsavel" id="responsavel-select" required>
                                        <option value="">Selecione o responsável</option>
                                        <?php foreach ($responsaveis as $responsavel): ?>
                                            <option value="<?= $responsavel ?>" <?= ($dados['responsavel'] == $responsavel) ? 'selected' : '' ?>>
                                                <?= $responsavel ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3" id="regime-tributario-group" style="display: none;">
                                    <label class="form-label">
                                        <i class="fas fa-briefcase me-1"></i>Regime Tributário
                                    </label>
                                    <select class="form-select" name="regime_tributario" id="regime_tributario">
                                        <option value="">Selecione o regime</option>
                                        <option value="Simples Nacional" <?= ($dados['regime_tributario'] ?? '') == 'Simples Nacional' ? 'selected' : '' ?>>Simples Nacional</option>
                                        <option value="Lucro Presumido" <?= ($dados['regime_tributario'] ?? '') == 'Lucro Presumido' ? 'selected' : '' ?>>Lucro Presumido</option>
                                        <option value="Lucro Real" <?= ($dados['regime_tributario'] ?? '') == 'Lucro Real' ? 'selected' : '' ?>>Lucro Real</option>
                                    </select>
                                    <div class="form-text">Obrigatório para Pessoa Jurídica</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-building me-1"></i>Destinação
                                    </label>
                                    <select class="form-select" name="destinacao" required>
                                        <option value="">Selecione a destinação</option>
                                        <?php foreach ($destinacoes as $destinacao): ?>
                                            <option value="<?= $destinacao ?>" <?= $dados['destinacao'] == $destinacao ? 'selected' : '' ?>>
                                                <?= $destinacao ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-building me-1"></i>Tipo de Obra
                                    </label>
                                    <select class="form-select" name="tipo_obra" required>
                                        <option value="">Selecione o tipo de obra</option>
                                        <?php foreach ($tipos_obra as $tipo): ?>
                                            <option value="<?= $tipo ?>" <?= $dados['tipo_obra'] == $tipo ? 'selected' : '' ?>>
                                                <?= $tipo ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-map me-1"></i>UF
                                    </label>
                                    <select class="form-select" name="uf" required>
                                        <option value="">Selecione</option>
                                        <?php foreach ($ufs as $uf): ?>
                                            <option value="<?= $uf ?>" <?= $dados['uf'] == $uf ? 'selected' : '' ?>>
                                                <?= $uf ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-ruler-combined me-1"></i>Área Construída (m²)</label>
                                    <input type="number" class="form-control" name="area" step="0.01" min="0" 
                                           value="<?= number_format($dados['area'], 2, '.', '') ?>" 
                                           placeholder="0,00" required>
                                    <div class="form-text">Área total construída em metros quadrados</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Após os campos principais do formulário, antes de Localização -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white py-3">
                            <h6 class="m-0 font-weight-bold text-white">
                                <i class="fas fa-calendar-alt me-2"></i>Data da Simulação e VAU
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>Data original da simulação:</strong>
                                <?= date('d/m/Y H:i', strtotime($dados['criado_em'])) ?>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="acao_data" id="manter_data" value="manter" checked>
                                <label class="form-check-label" for="manter_data">
                                    Manter data original (VAU permanece igual)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="acao_data" id="atualizar_data" value="atualizar">
                                <label class="form-check-label" for="atualizar_data">
                                    Atualizar para data atual (VAU será atualizado)
                                </label>
                            </div>
                            <div class="form-text text-warning mt-2">
                                <i class="fas fa-info-circle"></i>
                                Se atualizar a data, o VAU será recalculado conforme a tabela vigente no mês/UF atual.
                            </div>
                        </div>
                    </div>

                    <!-- Localização -->
                    <div class="card shadow mb-4" id="localizacao">
                        <div class="card-header bg-primary text-white py-3">
                            <h6 class="m-0 font-weight-bold text-white">
                                <i class="fas fa-map-marker-alt me-2"></i>Localização da Obra
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-city me-1"></i>Município
                                    </label>
                                    <input type="text" class="form-control" name="municipio" 
                                           value="<?= htmlspecialchars($dados['municipio']) ?>"
                                           placeholder="Ex: São Paulo">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-map-pin me-1"></i>Endereço
                                    </label>
                                    <input type="text" class="form-control" name="endereco" 
                                           value="<?= htmlspecialchars($dados['endereco']) ?>"
                                           placeholder="Ex: Rua das Flores, 123 - Centro">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botões de Ação -->
                    <div class="card shadow mb-4">
                        <div class="card-body text-center">
                            <button type="submit" class="btn btn-success btn-lg me-3">
                                <i class="fas fa-save me-2"></i>Salvar Alterações
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-lg me-2" onclick="window.history.back();">
								<i class="fas fa-arrow-left me-2"></i>Voltar
							</button>
                            <button type="button" class="btn btn-secondary btn-lg" onclick="window.history.back();">
								<i class="fas fa-times me-2"></i>Cancelar
							</button>
                        </div>
                    </div>
                </form>

            </div>
            <?php include 'footer_simple.php'; ?>
        </div>
    </div>
    <!-- /#wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle da Sidebar
        var menuToggle = document.getElementById('sidebar-toggle-btn');
        if (menuToggle) {
            menuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('wrapper').classList.toggle('toggled');
            });
        }

        function scrollToSection(sectionId) {
            document.getElementById(sectionId).scrollIntoView({ behavior: 'smooth' });
        }

        // Exibir/ocultar campo Regime Tributário
        function toggleRegimeTributario() {
            var resp = document.getElementById('responsavel-select').value;
            var regimeGroup = document.getElementById('regime-tributario-group');
            if (resp === 'Pessoa Jurídica') {
                regimeGroup.style.display = 'block';
                document.getElementById('regime_tributario').setAttribute('required', 'required');
            } else {
                regimeGroup.style.display = 'none';
                document.getElementById('regime_tributario').removeAttribute('required');
            }
        }
        
        document.getElementById('responsavel-select').addEventListener('change', toggleRegimeTributario);
        window.addEventListener('DOMContentLoaded', toggleRegimeTributario);

        // Exibir overlay ao submeter o formulário
        document.getElementById('formEdicao').addEventListener('submit', function() {
            document.getElementById('loading-overlay').style.display = 'flex';
        });

        // Limpar o overlay se o usuário voltar para a página usando o botão "Voltar"
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                document.getElementById('loading-overlay').style.display = 'none';
            }
        });
    </script>
    
</body>
</html>