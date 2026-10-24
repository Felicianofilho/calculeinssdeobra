<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';

// Verificar se pode acessar
if (!Auth::canAccessAdminPanel()) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview do Tema - Sistema INSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <style id="preview-css">
        /* CSS será injetado via JavaScript */
    </style>
</head>
<body>
    <!-- Navbar Preview -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-building me-2"></i>
                Calculadora INSS Obra Legal ®
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="#">
                    <i class="fas fa-user me-1"></i>Usuário
                </a>
                <a class="nav-link" href="#">
                    <i class="fas fa-sign-out-alt me-1"></i>Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Preview -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="#">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="#">
                                <i class="fas fa-calculator me-2"></i>Simulações
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="#">
                                <i class="fas fa-file-pdf me-2"></i>Relatórios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="#">
                                <i class="fas fa-cog me-2"></i>Configurações
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content Preview -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">Exportar</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">Imprimir</button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Simulações
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">150</div>
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
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Relatórios
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">45</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-file-pdf fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Usuários
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">12</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pendentes
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">8</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content Cards -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Últimas Simulações</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Destinação</th>
                                                <th>Valor INSS</th>
                                                <th>Data</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Obra Residencial SP</td>
                                                <td>Residencial</td>
                                                <td class="text-success">R$ 15.420,00</td>
                                                <td>15/01/2025</td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary">Ver</button>
                                                    <button class="btn btn-sm btn-outline-secondary">PDF</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Edifício Comercial RJ</td>
                                                <td>Comercial</td>
                                                <td class="text-success">R$ 28.750,00</td>
                                                <td>14/01/2025</td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary">Ver</button>
                                                    <button class="btn btn-sm btn-outline-secondary">PDF</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Galpão Industrial MG</td>
                                                <td>Industrial</td>
                                                <td class="text-success">R$ 42.180,00</td>
                                                <td>13/01/2025</td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary">Ver</button>
                                                    <button class="btn btn-sm btn-outline-secondary">PDF</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Ações Rápidas</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Nova Simulação
                                    </button>
                                    <button class="btn btn-success">
                                        <i class="fas fa-file-pdf me-2"></i>Gerar Relatório
                                    </button>
                                    <button class="btn btn-info">
                                        <i class="fas fa-download me-2"></i>Exportar Dados
                                    </button>
                                    <button class="btn btn-warning">
                                        <i class="fas fa-cog me-2"></i>Configurações
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Alertas</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Sistema funcionando normalmente
                                </div>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    3 simulações aguardando aprovação
                                </div>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Nova atualização disponível
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Aplicar configurações do preview
        function applyPreviewTheme() {
            const previewData = localStorage.getItem('themePreview');
            if (previewData) {
                const theme = JSON.parse(previewData);
                
                let css = `
                :root {
                    --primary-color: ${theme.primaryColor};
                    --secondary-color: ${theme.secondaryColor};
                    --success-color: ${theme.successColor};
                    --warning-color: ${theme.warningColor};
                    --danger-color: ${theme.dangerColor};
                    --info-color: ${theme.infoColor};
                    --navbar-bg: ${theme.navbarBg};
                    --sidebar-bg: ${theme.sidebarBg};
                    --font-family: ${theme.fontFamily};
                    --font-size-base: ${theme.fontSize};
                }
                
                body {
                    font-family: var(--font-family);
                    font-size: var(--font-size-base);
                }
                
                .navbar {
                    background: linear-gradient(135deg, var(--navbar-bg) 0%, var(--primary-color) 100%);
                }
                
                .bg-dark, .sidebar {
                    background-color: var(--sidebar-bg) !important;
                }
                
                .btn-primary {
                    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color) 100%);
                    border-color: var(--primary-color);
                }
                
                .btn-success {
                    background-color: var(--success-color);
                    border-color: var(--success-color);
                }
                
                .btn-warning {
                    background-color: var(--warning-color);
                    border-color: var(--warning-color);
                }
                
                .btn-danger {
                    background-color: var(--danger-color);
                    border-color: var(--danger-color);
                }
                
                .btn-info {
                    background-color: var(--info-color);
                    border-color: var(--info-color);
                }
                
                .text-primary { color: var(--primary-color) !important; }
                .text-success { color: var(--success-color) !important; }
                .text-warning { color: var(--warning-color) !important; }
                .text-danger { color: var(--danger-color) !important; }
                .text-info { color: var(--info-color) !important; }
                
                .bg-primary { background-color: var(--primary-color) !important; }
                .bg-success { background-color: var(--success-color) !important; }
                .bg-warning { background-color: var(--warning-color) !important; }
                .bg-danger { background-color: var(--danger-color) !important; }
                .bg-info { background-color: var(--info-color) !important; }
                
                .alert-primary {
                    background-color: rgba(44, 90, 160, 0.1);
                    border-color: var(--primary-color);
                    color: var(--primary-color);
                }
                
                .alert-success {
                    background-color: rgba(40, 167, 69, 0.1);
                    border-color: var(--success-color);
                    color: var(--success-color);
                }
                
                .alert-warning {
                    background-color: rgba(255, 193, 7, 0.1);
                    border-color: var(--warning-color);
                    color: var(--warning-color);
                }
                
                .alert-info {
                    background-color: rgba(23, 162, 184, 0.1);
                    border-color: var(--info-color);
                    color: var(--info-color);
                }
                `;
                
                document.getElementById('preview-css').textContent = css;
            }
        }

        // Aplicar tema ao carregar a página
        document.addEventListener('DOMContentLoaded', applyPreviewTheme);
    </script>
</body>
</html> 