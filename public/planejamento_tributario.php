<?php
/**
 * Interface Planejamento Tributário
 * Tela para planejamento tributário com fator de ajuste
 * 
 * @author Sistema INSS
 * @version 2.0
 */

// Configurações de segurança
require_once __DIR__ . '/../config/security.php';

session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';
require_once __DIR__ . '/../src/Models/Simulacao.php';
require_once __DIR__ . '/../src/Utils/CalculoINSS.php';

// Verificar autenticação
if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

$user = Auth::user();

// Bloquear acesso para leads
// if (Auth::isLead()) {
//     header('Location: minhas_simulacoes.php');
//     exit;
// }

// Receber ID da simulação via GET
$simulacao_id = $_GET['simulacao_id'] ?? '';

if (empty($simulacao_id)) {
    header('Location: minhas_simulacoes.php?error=dados_necessarios');
    exit;
}

// Buscar dados da simulação
$simulacao = Simulacao::find($simulacao_id);

if (!$simulacao || ($simulacao['user_id'] != $user['id'] && !Auth::isAdmin())) {
    header('Location: minhas_simulacoes.php?error=forbidden');
    exit;
}

// Verificar se é Pessoa Física
if (($simulacao['responsavel'] ?? '') !== 'Pessoa Física') {
    header('Location: relatorio_simulacao.php?id=' . $simulacao_id . '&error=tipo_nao_permitido');
    exit;
}

// Calcular dados da simulação
$dados_simulacao = $simulacao;
if (isset($simulacao['criado_em'])) {
    $dados_simulacao['data_simulacao'] = $simulacao['criado_em'];
}
$dados_calculo_inicial = CalculoINSS::calcular($dados_simulacao);

// Dados para o planejamento
$area_obra = $simulacao['area'];
$rmt_base = $dados_calculo_inicial['rmt'];
$inss_original = $dados_calculo_inicial['inss'];

// Carregar configurações
$config = LocalConfig::getAll();
$baseUrl = LocalConfig::getBaseUrl();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planejamento Tributário - Calculadora INSS Obra Legal ®</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <?php echo LocalConfig::getCustomCSS(); ?>
    
    <style>
        .planning-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e3d72 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 15px 15px;
        }

        .planning-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #dee2e6;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
        }
        
        .btn-calculate {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-calculate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
            color: white;
        }
        
        .btn-calculate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            border-radius: 12px;
        }
        
        .btn-calculate.btn-lg {
            min-height: 120px;
            font-size: 1.3rem;
        }
        
        .loading {
            display: none;
        }
        
        .form-check-input {
            cursor: pointer;
        }
        
        .form-check-label {
            cursor: pointer;
            user-select: none;
        }
        
        /* Melhorias no layout */
        .form-control-plaintext {
            border: 2px solid #e9ecef;
            transition: border-color 0.3s ease;
        }
        
        .form-control-plaintext:hover {
            border-color: var(--primary-color);
        }
        
        .currency-value {
            background-color: #f8f9fa;
            border: 2px solid #e9ecef;
        }
        
        .currency-value:focus {
            background-color: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
        }
        
        /* Responsividade para telas menores */
        @media (max-width: 768px) {
            .btn-calculate {
                margin-top: 1rem;
                width: 100% !important;
                min-height: 60px;
                font-size: 1.1rem;
            }
            
            .btn-calculate.btn-lg {
                min-height: 80px;
                font-size: 1.2rem;
            }
            
            .col-md-4 {
                margin-bottom: 1rem;
            }
            
            .form-check-label {
                font-size: 0.9rem;
            }
            
            .col-lg-4 {
                margin-top: 2rem;
            }
        }
        
        /* Melhorias para telas grandes */
        @media (min-width: 1200px) {
            .btn-calculate.btn-lg {
                min-height: 140px;
                font-size: 1.4rem;
            }
        }
        
        .table-planning {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #dee2e6;
        }
        
        .table-planning th {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e3d72 100%);
            color: #fff !important;
            font-weight: 600;
            text-align: center;
            padding: 1rem 0.5rem;
            border: none;
        }

        .table-planning td {
            text-align: center;
            padding: 0.8rem 0.5rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .table-planning tbody tr:hover {
            background-color: #f8f9fa;
        }

        .month-overdue {
            background-color: #ffebee !important;
            color: #dc3545;
            font-weight: bold;
        }

        .month-current {
            background-color: #fff3e0 !important;
            color: #fd7e14;
            font-weight: bold;
        }

        .month-future {
            background-color: #e8f5e8 !important;
            color: #28a745;
        }

        .total-row {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: bold;
            border-top: 2px solid var(--primary-color);
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-overdue {
            background-color: #dc3545;
            color: white;
        }

        .status-current {
            background-color: #fd7e14;
            color: white;
        }

        .status-future {
            background-color: #28a745;
            color: white;
        }

        .comparison-card {
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .comparison-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .currency-value {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

        .loading {
            display: none;
            margin-left: 0.5rem;
        }
        
        .alert-custom {
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        /* Corrige o rodapé para não ficar atrás da sidebar */
        @media (min-width: 768px) {
            #footer-main {
                margin-left: 240px; /* largura da sidebar */
            }
            #wrapper.toggled #footer-main {
                margin-left: 0;
            }
        }

        /* Estilos para o contêiner dos cards gerados dinamicamente */
        .comparison-cards-responsive {
            display: flex;
            flex-wrap: wrap;
            gap: 20px; /* Espaçamento entre os cards */
            justify-content: center; /* Centraliza os cards se houver espaço extra */
        }

        /* Estilos para os cards individuais gerados dinamicamente */
        .comparison-card-custom {
            box-sizing: border-box; /* Garante que padding e border sejam incluídos na largura */
            flex: 0 0 calc(20% - 16px); /* 5 colunas com espaçamento */
            max-width: calc(20% - 16px);
            /* Mantém os estilos visuais que já estavam no .comparison-card original */
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #dee2e6;
        }

        /* Responsividade para telas médias (2 colunas) */
        @media (max-width: 991.98px) {
            .comparison-card-custom {
                flex: 0 0 calc(50% - 10px); /* 2 colunas com espaçamento */
                max-width: calc(50% - 10px);
            }
        }

        /* Responsividade para telas pequenas (1 coluna) */
        @media (max-width: 767.98px) {
            .comparison-card-custom {
                flex: 0 0 100%; /* 1 coluna */
                max-width: 100%;
            }
        }

        /* Estilos para o contêiner dos cards gerados dinamicamente */
        .comparison-cards-responsive {
            display: flex;
            flex-wrap: wrap;
            gap: 20px; /* Espaçamento entre os cards */
            justify-content: center; /* Centraliza os cards se houver espaço extra */
        }

        /* Estilos para os cards individuais gerados dinamicamente */
        .comparison-card-custom {
            box-sizing: border-box;
            flex: 0 0 calc(20% - 16px); /* 5 colunas com espaçamento */
            max-width: calc(20% - 16px);
            /* Reaplicando estilos visuais do .comparison-card original */
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #dee2e6;
        }

        /* Responsividade para telas médias (2 colunas) */
        @media (max-width: 991.98px) {
            .comparison-card-custom {
                flex: 0 0 calc(50% - 10px); /* 2 colunas com espaçamento */
                max-width: calc(50% - 10px);
            }
        }

        /* Responsividade para telas pequenas (1 coluna) */
        @media (max-width: 767.98px) {
            .comparison-card-custom {
                flex: 0 0 100%; /* 1 coluna */
                max-width: 100%;
            }
        }

        /* Estilos para os cabeçalhos e corpos dos cards customizados */
        .card-header-custom {
            padding: 1rem;
            border-bottom: 1px solid rgba(0,0,0,.125);
            /* As cores de fundo e texto serão definidas pelo JS, mas aqui garantimos o padding/borda */
        }

        .card-body-custom {
            padding: 1.25rem;
        }

        .card-title-text {
            font-weight: 600;
        }

        .main-value {
            font-size: 1.875rem; /* 30px */
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .card-desc {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .comparison-card-custom:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
    </style>
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
                    <?php if (($user['user_type'] ?? 'lead') !== 'lead'): ?>
                    <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-tachometer-alt fa-fw me-2"></i><span class="sidebar-text">Dashboard</span></a>
                    <?php endif; ?>
                    <a href="nova_simulacao.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-plus fa-fw me-2"></i><span class="sidebar-text">Nova Simulação</span></a>
                    <a href="minhas_simulacoes.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-list-alt fa-fw me-2"></i><span class="sidebar-text">Minhas Simulações</span></a>
                    <?php if (($user['user_type'] ?? 'lead') !== 'lead'): ?>
                    <a href="meus_planejamentos.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-balance-scale me-2"></i><span class="sidebar-text">Meus Planejamentos</span></a>
                    <?php endif; ?>
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
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="mb-0 text-gray-800">
                        <i class="fas fa-calculator me-2"></i>Planejamento Tributário
                    </h1>
                    <div>
                        <a href="relatorio_simulacao.php?id=<?php echo urlencode($simulacao_id); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Voltar ao Relatório
                    </a>
                    </div>
                </div>

                <!-- Informações da Simulação -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="m-0 font-weight-bold">
                            <i class="fas fa-info-circle me-2"></i>Informações da Simulação
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Nome da Simulação:</strong> <?php echo htmlspecialchars($simulacao['nome'] ?? 'N/A'); ?></p>
                                <p class="mb-1"><strong>Responsável:</strong> <?php echo htmlspecialchars($simulacao['responsavel'] ?? 'N/A'); ?></p>
                                <p class="mb-1"><strong>Destinação:</strong> <?php echo htmlspecialchars($simulacao['destinacao'] ?? 'N/A'); ?></p>
                                <p class="mb-1"><strong>UF:</strong> <?php echo htmlspecialchars($simulacao['uf'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Tipo de Obra:</strong> <?php echo htmlspecialchars($simulacao['tipo_obra'] ?? 'N/A'); ?></p>
                                <p class="mb-1"><strong>Área:</strong> <?php echo number_format($simulacao['area'] ?? 0, 2, ',', '.'); ?> m²</p>
                                <p class="mb-1"><strong>RMT Base:</strong> R$ <?php echo number_format($rmt_base, 2, ',', '.'); ?></p>
                                <p class="mb-1"><strong>INSS Original:</strong> R$ <?php echo number_format($inss_original, 2, ',', '.'); ?></p>
                </div>
            </div>
        </div>
    </div>

                <!-- Formulário de Planejamento -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="m-0 font-weight-bold">
                            <i class="fas fa-cogs me-2"></i>Configurar Planejamento
                        </h5>
                    </div>
                    <div class="card-body">
                    <form id="formPlanning" 
                          data-area="<?php echo htmlspecialchars($simulacao['area'] ?? '80'); ?>"
                          data-uf="<?php echo htmlspecialchars($simulacao['uf'] ?? 'CE'); ?>"
                          data-destinacao="<?php echo htmlspecialchars($simulacao['destinacao'] ?? 'Res. Unifamiliar'); ?>"
                          data-rmt-base="<?php echo number_format($rmt_base, 2, '.', ''); ?>">
                        <div class="row">
                            <!-- Coluna Esquerda: Configurações -->
                            <div class="col-lg-8">
                                <!-- Dados Básicos -->
                                <div class="row mb-4">
                                    <!-- Área da Obra -->
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold mb-1">
                                            <i class="fas fa-ruler-combined me-2"></i>Área da Obra
                                        </label>
                                        <div class="form-control-plaintext bg-light p-3 rounded fs-5 text-center">
                                            <strong><?php echo number_format($area_obra, 2, ',', '.'); ?> m²</strong>
                                        </div>
                                        <small class="text-muted">Área fixa da simulação</small>
                                    </div>
                                    
                                    <!-- RMT Base -->
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold mb-1">
                                            <i class="fas fa-calculator me-2"></i>RMT Base
                                        </label>
                                        <input type="text" 
                                               class="form-control currency-value fs-5 p-3 text-center" 
                                               id="rmtBase" 
                                               readonly 
                                               value="R$ <?php echo number_format($rmt_base, 2, ',', '.'); ?>"
                                               placeholder="Calculado automaticamente">
                                        <small class="text-muted">Valor base para cálculos</small>
                                    </div>
                                    
                                    <!-- Concreto Usinado -->
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold mb-1">
                                            <i class="fas fa-concrete me-2"></i>Concreto Usinado?
                                        </label>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="concretoUsinado" 
                                                       style="width: 3rem; height: 1.5rem;" 
                                                       data-toggle="tooltip" 
                                                       title="Marque se a obra utilizará concreto usinado para redução da base de cálculo">
                                                <label class="form-check-label ms-3" for="concretoUsinado">
                                                    <span class="toggle-label-off text-muted">Não</span>
                                                    <span class="toggle-label-on text-primary fw-bold" style="display: none;">Sim</span>
                                                </label>
                                            </div>
                                        </div>
                                        <small class="text-muted text-center d-block">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Reduz base de cálculo INSS (IN RFB 2.021/2021)
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Período e Honorários -->
                                <div class="row mb-4">
                                    <!-- Período da Obra -->
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold mb-1">
                                            <i class="fas fa-calendar-alt me-2"></i>Período da Obra
                                        </label>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <input type="month" 
                                                       class="form-control p-3" 
                                                       id="mesInicio" 
                                                       required>
                                                <small class="text-muted">Mês/Ano Início</small>
                                            </div>
                                            <div class="col-6">
                                                <input type="month" 
                                                       class="form-control p-3" 
                                                       id="mesFim" 
                                                       required>
                                                <small class="text-muted">Mês/Ano Final</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Honorários -->
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold mb-1">
                                            <i class="fas fa-money-bill-wave me-2"></i>Honorários
                                        </label>
                                        <div class="input-group">
                                            <input type="number" class="form-control p-3" id="honorariosValor" min="0" step="0.01" value="0">
                                            <button class="btn btn-outline-secondary p-3" type="button" id="toggleTipoHonorario" data-tipo="real">R$</button>
                                        </div>
                                        <small class="text-muted">Defina o valor dos honorários</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Coluna Direita: Botão Calcular -->
                            <div class="col-lg-4">
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <button type="submit" class="btn btn-calculate btn-lg w-100 py-4">
                                        <i class="fas fa-cogs me-2"></i>Calcular Planejamento
                                        <div class="loading">
                                            <i class="fas fa-spinner fa-spin"></i>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                        </form>
                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                // Toggle dos honorários
                                const toggle = document.getElementById('toggleTipoHonorario');
                                if (toggle) {
                                    toggle.addEventListener('click', function() {
                                        if (toggle.dataset.tipo === 'real') {
                                            toggle.textContent = '%';
                                            toggle.dataset.tipo = 'percent';
                                        } else {
                                            toggle.textContent = 'R$';
                                            toggle.dataset.tipo = 'real';
                                        }
                                    });
                                }
                                
                                // Toggle do concreto usinado
                                const concretoToggle = document.getElementById('concretoUsinado');
                                const labelOff = document.querySelector('.toggle-label-off');
                                const labelOn = document.querySelector('.toggle-label-on');
                                
                                if (concretoToggle && labelOff && labelOn) {
                                    concretoToggle.addEventListener('change', function() {
                                        if (this.checked) {
                                            labelOff.style.display = 'none';
                                            labelOn.style.display = 'inline';
                                        } else {
                                            labelOff.style.display = 'inline';
                                            labelOn.style.display = 'none';
                                        }
                                    });
                                }
                            });
                            </script>
                    </div>
                </div>

                <!-- Mensagem de Loading -->
                        <div id="loadingMessage" class="alert alert-info text-center mt-3" style="display: none;">
                    <i class="fas fa-spinner fa-spin me-2"></i>
                    Calculando planejamento tributário...
        </div>

                <!-- Seção: RMT com Desconto -->
                <div id="rmtDiscountSection" class="planning-card" style="display: none;">
            <div class="row justify-content-center">
                        <div class="col-12">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white text-center">
                            <h5 class="mb-0">
                                <i class="fas fa-percentage me-2"></i>RMT com Desconto Aplicado
                            </h5>
                        </div>
                        <div class="card-body text-center">
                                    <div id="rmtDiscountDisplay"><!-- Conteúdo será preenchido dinamicamente --></div>
                                </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção: Planilha de Distribuição Mensal -->
                <div class="planning-card" id="planningSection" style="display: none;">
            <h2 class="mb-4">
                <i class="fas fa-table me-3 text-primary"></i>Distribuição Mensal
            </h2>

            <div class="table-responsive">
                        <table class="table table-planning">
                    <thead>
                        <tr>
                                    <th>MÊS/ANO</th>
                                    <th>REM. ATUAL.</th>
                                    <th>REM. ORIG.</th>
                                    <th>CPP</th>
                                    <th>MULTA</th>
                                    <th>SELIC</th>
                                    <th>MORA</th>
                            <th>MAED</th>
                                    <th>TOTAL</th>
                        </tr>
                    </thead>
                            <tbody id="tableData">
                        <!-- Dados serão inseridos dinamicamente -->
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td><strong>TOTAL</strong></td>
                                    <td id="totalRemAtual" class="currency-value">R$ 0,00</td>
                                    <td id="totalRemOrig" class="currency-value">R$ 0,00</td>
                                    <td id="totalCpp" class="currency-value">R$ 0,00</td>
                                    <td id="totalMulta" class="currency-value">R$ 0,00</td>
                            <td id="totalSelic">0,00%</td>
                                    <td id="totalMora" class="currency-value">R$ 0,00</td>
                                    <td id="totalMaed" class="currency-value">R$ 0,00</td>
                                    <td id="totalFinal" class="currency-value">R$ 0,00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Seção: Relatório Comparativo -->
                <div class="planning-card" id="comparisonSection" style="display: none;">
            <h2 class="mb-4">
                        <i class="fas fa-chart-bar me-3 text-primary"></i>Relatório Comparativo
            </h2>
            
            <div class="row">
                <!-- Cenário 1: Sem Fator de Ajuste -->
                <div class="col-md-4">
                            <div class="card comparison-card h-100 border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-times-circle me-2"></i>Sem Fator de Ajuste</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <h3 class="text-danger" id="valorSemFator">R$ 0,00</h3>
                                        <small class="text-muted">Valor Original</small>
                            </div>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-dot-circle text-danger me-2"></i>RMT integral aplicado</li>
                                <li><i class="fas fa-dot-circle text-danger me-2"></i>Sem descontos</li>
                                <li><i class="fas fa-dot-circle text-danger me-2"></i>Valor máximo a pagar</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Cenário 2: Com Fator de Ajuste -->
                <div class="col-md-4">
                            <div class="card comparison-card h-100 border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Com Fator de Ajuste</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <h3 class="text-success" id="valorComFator">R$ 0,00</h3>
                                <small class="text-muted">Valor Otimizado</small>
                            </div>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-dot-circle text-success me-2"></i>Desconto aplicado</li>
                                <li><i class="fas fa-dot-circle text-success me-2"></i>Planejamento otimizado</li>
                                <li><i class="fas fa-dot-circle text-success me-2"></i>Valor reduzido</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Cenário 3: Economia Obtida -->
                <div class="col-md-4">
                            <div class="card comparison-card h-100 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-piggy-bank me-2"></i>Economia Obtida</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <h3 class="text-warning" id="valorEconomia">R$ 0,00</h3>
                                <small class="text-muted">Redução Total</small>
                            </div>
                            <div class="text-center">
                                <div class="badge bg-warning text-dark fs-6 mb-2" id="percentualEconomia">0%</div>
                                <p class="small mb-0">de economia com o Fator de Ajuste</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resumo Executivo -->
            <div class="mt-4">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Resumo Executivo</h5>
                    </div>
                    <div class="card-body">
                                <div id="executiveSummary">
                            <!-- Conteúdo será preenchido dinamicamente -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Variáveis globais para o JavaScript
        window.simulacaoId = <?php echo json_encode($simulacao_id); ?>;
        window.areaObra = <?php echo json_encode($area_obra); ?>;
        window.rmtBase = <?php echo json_encode($rmt_base); ?>;
        window.inssOriginal = <?php echo json_encode($inss_original); ?>;
        window.userType = <?php echo json_encode($user['user_type'] ?? 'lead'); ?>;
        window.whatsappUrl = <?php echo json_encode(LocalConfig::get('company.social_media.whatsapp', 'https://wa.me/558534940140')); ?>;
    </script>
    <script src="assets/planejamento_tributario.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php include 'footer_simple.php'; ?>
</body>
</html>