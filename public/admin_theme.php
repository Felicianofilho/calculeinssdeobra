<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';

// Verificar se pode acessar configurações
if (!Auth::canAccessAdminPanel()) {
    header('Location: login.php');
    exit;
}

$isSuperAdmin = Auth::isSuperAdmin();
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
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações Temáticas - Sistema INSS</title>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo LocalConfig::buildAssetUrl($faviconFile); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <?php echo LocalConfig::getCustomCSS(); ?>
    <style>
        /* CSS específico para admin_theme - não afeta outras telas */
        .admin-theme-page .theme-section {
            background: var(--bs-primary);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            border-radius: 0.375rem;
        }
        
        .admin-theme-page .theme-section h4 {
            color: white !important;
        }
        
        .admin-theme-page .color-picker-group {
            background: white;
            border-radius: 0.5rem;
            padding: 1.25rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            margin-bottom: 1.5rem;
            border: 1px solid #dee2e6;
        }
        
        .admin-theme-page .color-picker-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .admin-theme-page .color-picker-item:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }
        
        .admin-theme-page .color-picker-label {
            flex: 1;
            font-weight: 500;
            color: #333;
            font-size: 0.875rem;
            margin-bottom: 0;
        }
        
        .admin-theme-page .color-picker-input {
            width: 38px;
            height: 26px;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.1);
        }
        
        .admin-theme-page .preview-section {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            margin-bottom: 1.5rem;
            border: 1px solid #dee2e6;
        }
        
        .admin-theme-page .preview-container {
            border: 2px dashed #dee2e6;
            border-radius: 0.375rem;
            padding: 1.5rem;
            min-height: 200px;
            background: #f8f9fa;
        }
        
        .admin-theme-page .font-selector {
            background: white;
            border-radius: 0.5rem;
            padding: 1.25rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            margin-bottom: 1.5rem;
            border: 1px solid #dee2e6;
        }
        
        .admin-theme-page .font-option {
            padding: 0.5rem;
            border: 2px solid #e9ecef;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .admin-theme-page .font-option:hover {
            border-color: var(--bs-primary);
            background: rgba(13, 110, 253, 0.05);
        }
        
        .admin-theme-page .font-option.selected {
            border-color: var(--bs-primary);
            background: rgba(13, 110, 253, 0.1);
        }
        
        .admin-theme-page .layout-options {
            background: white;
            border-radius: 0.5rem;
            padding: 1.25rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            margin-bottom: 1.5rem;
            border: 1px solid #dee2e6;
        }
        
        .admin-theme-page .layout-option {
            text-align: center;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 0.5rem;
        }
        
        .admin-theme-page .layout-option:hover {
            border-color: var(--bs-primary);
            transform: translateY(-1px);
        }
        
        .admin-theme-page .layout-option.selected {
            border-color: var(--bs-primary);
            background: rgba(13, 110, 253, 0.1);
        }
        
        .admin-theme-page .action-buttons {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 0.75rem 0;
            border-top: 1px solid #dee2e6;
            z-index: 1000;
        }
        
        .admin-theme-page .btn-modern {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.875rem;
            white-space: nowrap;
            min-width: 44px;
        }
        
        .admin-theme-page .btn-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.15);
        }
        
        /* Responsividade dos botões */
        @media (max-width: 576px) {
            .admin-theme-page .card-header {
                flex-direction: column;
                gap: 10px;
                align-items: stretch !important;
            }
            
            .admin-theme-page .card-header h6 {
                text-align: center;
                margin-bottom: 0;
            }
            
            .admin-theme-page .btn-modern {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
                flex: 1;
                min-width: auto;
            }
            
            .admin-theme-page .btn-modern i {
                margin-right: 0 !important;
            }
        }
        
        @media (max-width: 768px) {
            .admin-theme-page .btn-modern {
                padding: 0.5rem 0.75rem;
            }
        }
        
        .admin-theme-page .slider-container {
            margin: 0.75rem 0;
        }
        
        .admin-theme-page .slider-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.375rem;
            font-size: 0.875rem;
        }
        
        .admin-theme-page .slider-value {
            font-weight: 600;
            color: var(--bs-primary);
        }
        
        .admin-theme-page input[type="range"] {
            width: 100%;
            height: 4px;
            border-radius: 2px;
            background: #e9ecef;
            outline: none;
        }
        
        .admin-theme-page input[type="range"]::-webkit-slider-thumb {
            appearance: none;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--bs-primary);
            cursor: pointer;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.15);
        }
        
        /* Correções da Sidebar */
        #sidebar-wrapper {
            overflow: hidden;
        }
        
        #sidebar-wrapper .list-group-item {
            border: none !important;
            border-radius: 0 !important;
            margin: 0 !important;
            padding: 0.75rem 1rem !important;
        }
        
        #sidebar-wrapper .list-group-item:hover {
            background-color: rgba(255,255,255,0.1) !important;
            padding-left: 1.5rem !important;
            border-left: 3px solid var(--primary-color) !important;
        }
        
        #sidebar-wrapper .list-group-item.active {
            background-color: var(--primary-color) !important;
            border-left: 3px solid #fff !important;
        }

        .sidebar-heading img,
        img[alt="Logo"] {
            background: #fff !important;
            border-radius: 8px;
            padding: 8px;
        }
    </style>
</head>
<body class="admin-theme-page">
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-dark" id="sidebar-wrapper">
            <div class="sidebar-content-wrapper">
                <div class="sidebar-heading text-center py-4" style="background: none;">
                    <?php if (!empty($logoFile)): ?>
                        <img class="sidebar-logo" src="<?php echo LocalConfig::buildAssetUrl($logoFile); ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <span class="text-white fw-bold fs-5"><?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush">
                    <a href="javascript:void(0);" onclick="window.history.back();" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-arrow-left fa-fw me-2"></i><span class="sidebar-text">Voltar</span></a>
                    <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-tachometer-alt fa-fw me-2"></i><span class="sidebar-text">Dashboard</span></a>
                    <a href="admin_theme.php" class="list-group-item list-group-item-action bg-dark text-white active"><i class="fas fa-palette fa-fw me-2"></i><span class="sidebar-text">Temas</span></a>
                    <a href="admin_settings.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-cog fa-fw me-2"></i><span class="sidebar-text">Configurações</span></a>
                    <a href="logout.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-sign-out-alt fa-fw me-2"></i><span class="sidebar-text">Sair</span></a>
                </div>
            </div>
            <div class="sidebar-toggler-wrapper">
                <button class="sidebar-toggler" id="sidebar-toggle-btn">
                    <i class="fas fa-angle-double-left"></i>
                </button>
            </div>
        </div>
        <!-- Main content -->
        <div id="page-content-wrapper" class="flex-grow-1 p-4">
            <div class="container-fluid">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-palette me-2"></i>Configurações de Tema e Visual
                    </h1>
                    <div class="d-flex gap-2">
                        <a href="admin.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Voltar
                        </a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-palette me-2"></i>Configurações de Tema e Visual</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-light btn-modern" onclick="resetFormTheme()">
                                        <i class="fas fa-undo me-1" style="color: black !important;"></i><span class="d-none d-sm-inline" style="color: black !important;">Resetar</span>
                                    </button>
                                    <button class="btn btn-danger btn-modern" onclick="restoreDefaultTheme()">
                                        <i class="fas fa-history me-1"></i><span class="d-none d-sm-inline">Restaurar Padrão</span>
                                    </button>
                                    <button class="btn btn-warning btn-modern" onclick="previewTheme()">
                                        <i class="fas fa-eye me-1" style="color: black !important;"></i><span class="d-none d-sm-inline" style="color: black !important;">Preview</span>
                                    </button>
                                    <button class="btn btn-success btn-modern" onclick="saveTheme()">
                                        <i class="fas fa-save me-1"></i><span class="d-none d-sm-inline">Salvar</span>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3 align-items-start">
                                    <!-- Coluna esquerda: blocos de configuração -->
                                    <div class="col-lg-8 d-flex flex-column gap-3">
                                        <div class="branding-options card p-2 mb-0">
                                            <div class="card-header bg-primary text-white py-2">
                                                <h6 class="m-0" style="font-size:1rem"><i class="fas fa-image me-2"></i>Logo e Favicon</h6>
                                            </div>
                                            <div class="card-body p-2">
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-4 mb-0">
                                                        <label class="form-label fw-bold small mb-1">Logo (300px)</label>
                                                        <input type="file" class="form-control form-control-sm" id="logoInput" accept="image/png">
                                                        <small class="text-muted">PNG, 300px largura</small>
                                                        <div class="mt-1" id="logoPreview"></div>
                                                    </div>
                                                    <div class="col-md-4 mb-0">
                                                        <label class="form-label fw-bold small mb-1">Favicon (64x64px)</label>
                                                        <input type="file" class="form-control form-control-sm" id="faviconInput" accept="image/png">
                                                        <small class="text-muted">PNG, 64x64 pixels</small>
                                                        <div class="mt-1" id="faviconPreview"></div>
                                                    </div>
                                                    <div class="col-md-4 mb-0">
                                                        <label class="form-label fw-bold small mb-1">Logo Relatório (Max 300px)</label>
                                                        <input type="file" class="form-control form-control-sm" id="logoReportInput" accept="image/png">
                                                        <small class="text-muted">PNG, máx 300px largura</small>
                                                        <div class="mt-1" id="logoReportPreview"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Card Paleta de Cores e Tipografia -->
                                        <div class="card p-2 mb-0">
                                            <div class="card-header bg-primary text-white py-2">
                                                <h6 class="m-0" style="font-size:1rem"><i class="fas fa-palette me-2"></i>Paleta de Cores e Tipografia</h6>
                                            </div>
                                            <div class="card-body p-3">
                                                <div class="row g-3">
                                                    <!-- Paleta de Cores -->
                                                    <div class="col-lg-6">
                                                        <div class="color-picker-group border-0 shadow-none p-0">
                                                            <h5 class="mb-3">
                                                                <i class="fas fa-palette me-2 text-primary"></i>Paleta de Cores
                                                            </h5>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="color-picker-item">
                                                                        <div class="color-picker-label">Cor Primária</div>
                                                                        <input type="color" class="color-picker-input" id="primaryColor" 
                                                                               value="<?php echo $config['theme']['colors']['primary'] ?? '#2c5aa0'; ?>"
                                                                               onchange="updatePreview()">
																	</div>
																	<div class="color-picker-item">
																		<div class="color-picker-label">Texto das Barras de Título</div>
																		<input type="color" class="color-picker-input" id="cardHeaderTextColor"
																			   value="<?php echo $config['theme']['colors']['card_header_text'] ?? '#fff'; ?>"
																			   onchange="updatePreview()">										
                                                                    </div>
                                                                    <div class="color-picker-item">
                                                                        <div class="color-picker-label">Cor de Sucesso</div>
                                                                        <input type="color" class="color-picker-input" id="successColor" 
                                                                               value="<?php echo $config['theme']['colors']['success'] ?? '#28a745'; ?>"
                                                                               onchange="updatePreview()">
                                                                    </div>
                                                                    <div class="color-picker-item">
                                                                        <div class="color-picker-label">Cor de Aviso</div>
                                                                        <input type="color" class="color-picker-input" id="warningColor" 
                                                                               value="<?php echo $config['theme']['colors']['warning'] ?? '#ffc107'; ?>"
                                                                               onchange="updatePreview()">
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="color-picker-item">
                                                                        <div class="color-picker-label">Cor de Perigo</div>
                                                                        <input type="color" class="color-picker-input" id="dangerColor" 
                                                                               value="<?php echo $config['theme']['colors']['danger'] ?? '#dc3545'; ?>"
                                                                               onchange="updatePreview()">
                                                                    </div>
                                                                    <div class="color-picker-item">
                                                                        <div class="color-picker-label">Cor de Informação</div>
                                                                        <input type="color" class="color-picker-input" id="infoColor" 
                                                                               value="<?php echo $config['theme']['colors']['info'] ?? '#17a2b8'; ?>"
                                                                               onchange="updatePreview()">
                                                                    </div>
                                                                    <div class="color-picker-item">
                                                                        <div class="color-picker-label">Sidebar</div>
                                                                        <input type="color" class="color-picker-input" id="sidebarBg" 
                                                                               value="<?php echo $config['theme']['colors']['sidebar_bg'] ?? '#212529'; ?>"
                                                                               onchange="updatePreview()">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Tipografia -->
                                                    <div class="col-lg-6">
                                                        <div class="font-selector border-0 shadow-none p-0">
                                                            <h5 class="mb-3">
                                                                <i class="fas fa-font me-2 text-primary"></i>Tipografia
                                                            </h5>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <label class="form-label fw-bold small">Família da Fonte</label>
                                                                    <select class="form-select form-select-sm" id="fontFamily" onchange="updatePreview()">
                                                                        <option value="Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" 
                                                                                <?php echo ($config['theme']['fonts']['family'] ?? '') === 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif' ? 'selected' : ''; ?>>
                                                                            Inter (Recomendado)
                                                                        </option>
                                                                        <option value="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                                                                <?php echo ($config['theme']['fonts']['family'] ?? '') === "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" ? 'selected' : ''; ?>>
                                                                            Segoe UI
                                                                        </option>
                                                                        <option value="'Roboto', sans-serif"
                                                                                <?php echo ($config['theme']['fonts']['family'] ?? '') === "'Roboto', sans-serif" ? 'selected' : ''; ?>>
                                                                            Roboto
                                                                        </option>
                                                                        <option value="'Open Sans', sans-serif"
                                                                                <?php echo ($config['theme']['fonts']['family'] ?? '') === "'Open Sans', sans-serif" ? 'selected' : ''; ?>>
                                                                            Open Sans
                                                                        </option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label fw-bold small">Tamanho Base da Fonte</label>
                                                                    <div class="slider-container">
                                                                        <div class="slider-label">
                                                                            <span>Pequeno</span>
                                                                            <span class="slider-value" id="fontSizeValue">16px</span>
                                                                            <span>Grande</span>
                                                                        </div>
                                                                        <input type="range" id="fontSize" min="12" max="20" value="16" onchange="updateFontSize()">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="layout-options mt-3">
                                                                <h6 class="mb-2">
                                                                    <i class="fas fa-th-large me-2 text-primary"></i>Configurações de Layout
                                                                </h6>
                                                                <div class="row">
                                                                    <div class="col-md-4">
                                                                        <label class="form-label fw-bold small"><i class="fas fa-corner me-1"></i>Raio</label>
                                                                        <div class="slider-container">
                                                                            <div class="slider-label">
                                                                                <span>0px</span>
                                                                                <span class="slider-value" id="borderRadiusValue">6px</span>
                                                                                <span>20px</span>
                                                                            </div>
                                                                            <input type="range" id="borderRadius" min="0" max="20" value="6" onchange="updateBorderRadius()">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <label class="form-label fw-bold small"><i class="fas fa-arrows-alt-h me-1"></i>Espaço</label>
                                                                        <div class="slider-container">
                                                                            <div class="slider-label">
                                                                                <span>Comp.</span>
                                                                                <span class="slider-value" id="paddingValue">1rem</span>
                                                                                <span>Esp.</span>
                                                                            </div>
                                                                            <input type="range" id="padding" min="0.5" max="2" step="0.25" value="1" onchange="updatePadding()">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <label class="form-label fw-bold small"><i class="fas fa-cloud-meatball me-1"></i>Sombra</label>
                                                                        <div class="slider-container">
                                                                            <div class="slider-label">
                                                                                <span>Suave</span>
                                                                                <span class="slider-value" id="shadowValue">Normal</span>
                                                                                <span>Forte</span>
                                                                            </div>
                                                                            <input type="range" id="shadow" min="0" max="2" step="0.5" value="1" onchange="updateShadow()">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="layout-options">
                                            <h5 class="mb-3">
                                                <i class="fas fa-th-large me-2 text-primary"></i>Layout
                                            </h5>
                                            <div class="row">
                                                <div class="col-12">
                                                    <label class="form-label fw-bold small">Posição da Sidebar</label>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="layout-option" data-layout="left" onclick="selectLayout('left')">
                                                                <i class="fas fa-arrow-left fa-2x mb-2 text-primary"></i>
                                                                <div>Esquerda</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="layout-option" data-layout="right" onclick="selectLayout('right')">
                                                                <i class="fas fa-arrow-right fa-2x mb-2 text-primary"></i>
                                                                <div>Direita</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Coluna direita: preview -->
                                    <div class="col-lg-4 align-self-start mb-0">
                                        <div class="preview-section h-100 d-flex flex-column justify-content-start sticky-top" style="top: 24px;">
                                            <h5 class="mb-3">
                                                <i class="fas fa-eye me-2 text-primary"></i>Preview em Tempo Real
                                            </h5>
                                            <div class="preview-container flex-grow-1">
                                                <!-- Exemplo de Card -->
                                                <div class="card mb-3" style="box-shadow: var(--card-shadow, 0 0.125rem 0.25rem rgba(0,0,0,0.075)); border-radius: var(--border-radius, 0.5rem);">
                                                    <div class="card-header bg-primary text-white">
                                                        <h6 class="m-0">Exemplo de Card</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <p class="mb-3">Este é um exemplo de card, mostrando borda, sombra e arredondamento conforme as configurações.</p>
                                                        <div class="row g-2 mb-2">
                                                            <div class="col-6 d-grid">
                                                                <button class="btn btn-primary btn-sm">Salvar</button>
                                                            </div>
                                                            <div class="col-6 d-grid">
                                                                <button class="btn btn-secondary btn-sm">Cancelar</button>
                                                            </div>
                                                            <div class="col-6 d-grid">
                                                                <button class="btn btn-success btn-sm">Confirmar</button>
                                                            </div>
                                                            <div class="col-6 d-grid">
                                                                <button class="btn btn-warning btn-sm">Atenção</button>
                                                            </div>
                                                            <div class="col-6 d-grid">
                                                                <button class="btn btn-danger btn-sm">Erro</button>
                                                            </div>
                                                            <div class="col-6 d-grid">
                                                                <button class="btn btn-info btn-sm">Info</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Exemplo de Alertas -->
                                                <div class="mb-2">
                                                    <div class="alert alert-success py-2 mb-1"><i class="fas fa-check-circle me-1"></i> Operação realizada com sucesso!</div>
                                                    <div class="alert alert-warning py-2 mb-1"><i class="fas fa-exclamation-triangle me-1"></i> Atenção: revise os dados informados.</div>
                                                    <div class="alert alert-danger py-2 mb-1"><i class="fas fa-times-circle me-1"></i> Ocorreu um erro ao salvar.</div>
                                                    <div class="alert alert-info py-2 mb-1"><i class="fas fa-info-circle me-1"></i> Esta é uma mensagem informativa.</div>
                                                </div>
                                                <div class="text-muted small mt-2">
                                                    As mudanças são aplicadas em tempo real conforme você ajusta os controles.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer_simple.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Inicializar seleções de layout
        document.addEventListener('DOMContentLoaded', function() {
            selectLayout('<?php echo $config['theme']['layout']['sidebar_position'] ?? 'left'; ?>');
        });

        function updatePreview() {
            const primaryColor = document.getElementById('primaryColor').value;
            const successColor = document.getElementById('successColor').value;
            const warningColor = document.getElementById('warningColor').value;
            const dangerColor = document.getElementById('dangerColor').value;
            const infoColor = document.getElementById('infoColor').value;
            const sidebarBg = document.getElementById('sidebarBg').value;
            const fontFamily = document.getElementById('fontFamily').value;
            const fontSize = document.getElementById('fontSize').value + 'px';
            const cardHeaderTextColor = document.getElementById('cardHeaderTextColor').value;

            // Aplicar mudanças em tempo real
            document.documentElement.style.setProperty('--primary-color', primaryColor);
            document.documentElement.style.setProperty('--success-color', successColor);
            document.documentElement.style.setProperty('--warning-color', warningColor);
            document.documentElement.style.setProperty('--danger-color', dangerColor);
            document.documentElement.style.setProperty('--info-color', infoColor);
            document.documentElement.style.setProperty('--sidebar-bg', sidebarBg);
            document.documentElement.style.setProperty('--font-family', fontFamily);
            document.documentElement.style.setProperty('--font-size-base', fontSize);
            document.documentElement.style.setProperty('--card-header-text', cardHeaderTextColor);
        }

        function updateFontSize() {
            const fontSize = document.getElementById('fontSize').value;
            document.getElementById('fontSizeValue').textContent = fontSize + 'px';
            updatePreview();
        }
        
        function updateBorderRadius() {
            const borderRadius = document.getElementById('borderRadius').value;
            document.getElementById('borderRadiusValue').textContent = borderRadius + 'px';
            document.documentElement.style.setProperty('--border-radius', borderRadius + 'px');
        }
        
        function updatePadding() {
            const padding = document.getElementById('padding').value;
            document.getElementById('paddingValue').textContent = padding + 'rem';
            document.documentElement.style.setProperty('--padding-medium', padding + 'rem');
            document.documentElement.style.setProperty('--padding-large', (parseFloat(padding) * 1.5) + 'rem');
        }
        
        function updateShadow() {
            const shadow = document.getElementById('shadow').value;
            let shadowText = 'Normal';
            let shadowValue = '0 0.125rem 0.25rem rgba(0, 0, 0, 0.075)';
            
            if (shadow == 0) {
                shadowText = 'Suave';
                shadowValue = '0 0.0625rem 0.125rem rgba(0, 0, 0, 0.05)';
            } else if (shadow == 1.5) {
                shadowText = 'Forte';
                shadowValue = '0 0.25rem 0.5rem rgba(0, 0, 0, 0.1)';
            } else if (shadow == 2) {
                shadowText = 'Muito Forte';
                shadowValue = '0 0.5rem 1rem rgba(0, 0, 0, 0.15)';
            }
            
            document.getElementById('shadowValue').textContent = shadowText;
            document.documentElement.style.setProperty('--card-shadow', shadowValue);
        }

        function selectLayout(position) {
            document.querySelectorAll('[data-layout]').forEach(option => {
                option.classList.remove('selected');
            });
            document.querySelector(`[data-layout="${position}"]`).classList.add('selected');
        }

        function resetFormTheme() {
            // Recarrega a página para restaurar os valores do último salvo
            location.reload();
        }

        function restoreDefaultTheme() {
            if (confirm('Tem certeza que deseja restaurar o visual padrão do sistema? Isso irá sobrescrever todas as configurações visuais, mas manterá as logos.')) {
                fetch('admin_theme_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'resetar_tema' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        localStorage.setItem('themeResetSuccess', '1');
                        location.reload();
                    } else {
                        alert('Erro: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao restaurar padrão');
                });
            }
        }

        // Toast de sucesso após reload
        window.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('themeResetSuccess') === '1') {
                showToast('Tema visual restaurado para o padrão com sucesso!');
                localStorage.removeItem('themeResetSuccess');
            }
        });

        function showToast(msg) {
            let toast = document.createElement('div');
            toast.textContent = msg;
            toast.style.position = 'fixed';
            toast.style.top = '24px';
            toast.style.left = '50%';
            toast.style.transform = 'translateX(-50%)';
            toast.style.background = '#28a745';
            toast.style.color = '#fff';
            toast.style.padding = '12px 32px';
            toast.style.borderRadius = '8px';
            toast.style.fontWeight = 'bold';
            toast.style.fontSize = '1rem';
            toast.style.boxShadow = '0 2px 12px rgba(0,0,0,0.15)';
            toast.style.zIndex = 9999;
            toast.style.opacity = '0.97';
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.transition = 'opacity 0.5s';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }

        function previewTheme() {
            // Abrir preview em nova aba
            const previewData = {
                primaryColor: document.getElementById('primaryColor').value,
                successColor: document.getElementById('successColor').value,
                warningColor: document.getElementById('warningColor').value,
                dangerColor: document.getElementById('dangerColor').value,
                infoColor: document.getElementById('infoColor').value,
                sidebarBg: document.getElementById('sidebarBg').value,
                fontFamily: document.getElementById('fontFamily').value,
                fontSize: document.getElementById('fontSize').value + 'px',
                cardHeaderTextColor: document.getElementById('cardHeaderTextColor').value
            };
            
            localStorage.setItem('themePreview', JSON.stringify(previewData));
            window.open('theme_preview.php', '_blank');
        }

        function saveTheme() {
            const themeData = {
                action: 'salvar_configuracoes_tematicas',
                configs: {
                    theme: {
                        colors: {
                            primary: document.getElementById('primaryColor').value,
                            success: document.getElementById('successColor').value,
                            warning: document.getElementById('warningColor').value,
                            danger: document.getElementById('dangerColor').value,
                            info: document.getElementById('infoColor').value,
                            sidebar_bg: document.getElementById('sidebarBg').value,
                            card_header_text: document.getElementById('cardHeaderTextColor').value
                        },
                        fonts: {
                            family: document.getElementById('fontFamily').value,
                            size_base: document.getElementById('fontSize').value + 'px'
                        },
                        layout: {
                            sidebar_position: document.querySelector('[data-layout].selected').dataset.layout
                        }
                    }
                }
            };

            fetch('admin_theme_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(themeData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Tema salvo com sucesso! Recarregue a página para ver as mudanças.');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar tema');
            });
        }

        function uploadImageAJAX(input, type, previewId) {
            const file = input.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', type);
            formData.append('action', 'upload_logo_favicon');
            fetch('admin_theme_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(previewId).innerHTML = `<img src="${data.path}" alt="Preview" style="max-width:100%;max-height:100px;">`;
                    alert('Imagem salva com sucesso!');
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(() => alert('Erro ao enviar imagem.'));
        }

        function validateAndPreviewLogo(input) {
            const file = input.files[0];
            if (!file) return;
            if (file.type !== 'image/png') {
                alert('A imagem deve ser PNG.');
                input.value = '';
                return;
            }
            const img = new Image();
            img.onload = function() {
                if (img.width !== 300 || img.height > 300) {
                    alert('A logo deve ter exatamente 300px de largura e até 300px de altura.');
                    input.value = '';
                    document.getElementById('logoPreview').innerHTML = '';
                    return;
                }
                // Preview local
                document.getElementById('logoPreview').innerHTML = `<img src="${img.src}" alt="Preview" style="max-width:100%;max-height:100px;">`;
                // Upload AJAX
                uploadImageAJAX(input, 'logo', 'logoPreview');
            };
            img.onerror = function() {
                alert('Arquivo inválido.');
                input.value = '';
                document.getElementById('logoPreview').innerHTML = '';
            };
            img.src = URL.createObjectURL(file);
        }

        function validateAndPreviewImage(input, width, height, previewId) {
            const file = input.files[0];
            if (!file) return;
            if (file.type !== 'image/png') {
                alert('A imagem deve ser PNG.');
                input.value = '';
                return;
            }
            const img = new Image();
            img.onload = function() {
                if (img.width !== width || img.height !== height) {
                    alert(`A imagem deve ter exatamente ${width}x${height} pixels.`);
                    input.value = '';
                    document.getElementById(previewId).innerHTML = '';
                    return;
                }
                document.getElementById(previewId).innerHTML = `<img src="${img.src}" alt="Preview" style="max-width:100%;max-height:60px;">`;
                uploadImageAJAX(input, 'favicon', previewId);
            };
            img.onerror = function() {
                alert('Arquivo inválido.');
                input.value = '';
                document.getElementById(previewId).innerHTML = '';
            };
            img.src = URL.createObjectURL(file);
        }

        function validateAndPreviewLogoReport(input) {
            const file = input.files[0];
            if (!file) return;

            if (file.type !== 'image/png') {
                alert('A imagem deve ser do tipo PNG.');
                input.value = '';
                return;
            }

            if (file.size > 7168) { // 7 KB = 7 * 1024 bytes
                alert('O arquivo da imagem não pode exceder 7 KB.');
                input.value = '';
                return;
            }

            const img = new Image();
            img.onload = function() {
                if (img.width > 300) {
                    alert('A largura da imagem não pode exceder 300 pixels.');
                    input.value = '';
                    document.getElementById('logoReportPreview').innerHTML = '';
                    return;
                }
                document.getElementById('logoReportPreview').innerHTML = `<img src="${img.src}" alt="Preview" style="max-width:100%;max-height:120px;">`;
                uploadImageAJAX(input, 'logo_report', 'logoReportPreview');
            };
            img.onerror = function() {
                alert('Arquivo de imagem inválido ou corrompido.');
                input.value = '';
                document.getElementById('logoReportPreview').innerHTML = '';
            };
            img.src = URL.createObjectURL(file);
        }

        document.getElementById('logoInput').addEventListener('change', function() {
            validateAndPreviewLogo(this);
        });
        document.getElementById('faviconInput').addEventListener('change', function() {
            validateAndPreviewImage(this, 64, 64, 'faviconPreview');
        });
        document.getElementById('logoReportInput').addEventListener('change', function() {
            validateAndPreviewLogoReport(this);
        });
    </script>
</body>
</html> 